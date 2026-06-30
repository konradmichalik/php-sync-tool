<?php

declare(strict_types=1);

/*
 * This file is part of the "php-sync-tool" Composer package.
 *
 * (c) 2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\SyncTool\Config;

use KonradMichalik\SyncTool\Exception\{ConfigException, NoConfigFoundException};

use function array_key_exists;
use function dirname;
use function is_array;
use function is_string;
use function sprintf;

/**
 * ConfigResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigResolver
{
    private const PROJECT_CONFIG_DIR = '.sync-tool';
    private const HOSTS_FILE = 'hosts.yaml';
    private const DEFAULTS_FILE = 'defaults.yaml';

    /** @var array<string, HostDefinition> */
    private array $globalHosts = [];

    /** @var array<string, mixed> */
    private array $globalDefaults = [];

    /** @var array<string, mixed> */
    private array $projectDefaults = [];

    /** @var array<string, ProjectConfig> */
    private array $projectConfigs = [];

    private bool $loaded = false;

    public function __construct(
        private readonly ConfigLoader $loader = new ConfigLoader(),
        private readonly ?string $homeDir = null,
        private readonly ?string $workingDir = null,
    ) {}

    public function resolve(?string $configFile = null, ?string $origin = null, ?string $target = null, ?string $hostFile = null): ResolvedConfig
    {
        $this->load();

        if (null !== $hostFile && '' !== $hostFile) {
            $this->mergeHostFile($hostFile);
        }

        if (null !== $configFile && '' !== $configFile) {
            return $this->resolveExplicitFile($configFile);
        }

        $hasOrigin = null !== $origin && '' !== $origin;
        $hasTarget = null !== $target && '' !== $target;

        if ($hasOrigin && !$hasTarget && isset($this->projectConfigs[$origin])) {
            return $this->resolveProjectConfig($this->projectConfigs[$origin]);
        }

        if ($hasOrigin && $hasTarget) {
            return $this->resolveHostReferences($origin, $target);
        }

        throw new NoConfigFoundException('Configuration is missing, use a separate file or provide host parameter');
    }

    /**
     * @return array<string, ProjectConfig>
     */
    public function getProjectConfigs(): array
    {
        $this->load();

        return $this->projectConfigs;
    }

    /**
     * @return array<string, HostDefinition>
     */
    public function getGlobalHosts(): array
    {
        $this->load();

        return $this->globalHosts;
    }

    private function mergeHostFile(string $hostFile): void
    {
        if (!is_file($hostFile)) {
            throw new ConfigException(sprintf('Host file not found: %s', $hostFile));
        }

        foreach ($this->loader->load($hostFile) as $name => $hostData) {
            if (is_array($hostData)) {
                $this->globalHosts[(string) $name] = HostDefinition::fromArray((string) $name, $hostData);
            }
        }
    }

    private function resolveExplicitFile(string $configFile): ResolvedConfig
    {
        if (!is_file($configFile)) {
            throw new ConfigException(sprintf('Configuration file not found: %s', $configFile));
        }

        return new ResolvedConfig(configFile: $configFile, source: sprintf('explicit file: %s', $configFile));
    }

    private function resolveProjectConfig(ProjectConfig $project): ResolvedConfig
    {
        return new ResolvedConfig(
            configFile: $project->filePath,
            originConfig: $this->resolveEndpoint($project->origin),
            targetConfig: $this->resolveEndpoint($project->target),
            mergedConfig: $this->mergeDefaults($project->config),
            source: sprintf('project config: %s', $project->name),
        );
    }

    private function resolveHostReferences(string $origin, string $target): ResolvedConfig
    {
        if (!isset($this->globalHosts[$origin])) {
            throw new ConfigException($this->hostNotFoundMessage($origin));
        }

        if (!isset($this->globalHosts[$target])) {
            throw new ConfigException($this->hostNotFoundMessage($target));
        }

        return new ResolvedConfig(
            originConfig: $this->globalHosts[$origin]->toClientConfig(),
            targetConfig: $this->globalHosts[$target]->toClientConfig(),
            mergedConfig: $this->mergeDefaults([]),
            source: sprintf('host references: %s → %s', $origin, $target),
        );
    }

    /**
     * @param string|array<string, mixed>|null $endpoint
     *
     * @return array<string, mixed>
     */
    private function resolveEndpoint(string|array|null $endpoint): array
    {
        if (null === $endpoint) {
            return [];
        }

        if (is_string($endpoint)) {
            if (isset($this->globalHosts[$endpoint])) {
                return $this->globalHosts[$endpoint]->toClientConfig();
            }

            throw new ConfigException($this->hostNotFoundMessage($endpoint));
        }

        return $endpoint;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function mergeDefaults(array $config): array
    {
        $merged = $this->deepMerge($this->globalDefaults, $this->projectDefaults);

        return $this->deepMerge($merged, $config);
    }

    /**
     * Recurse only when both sides are maps; otherwise the overlay value
     * replaces (so lists fully replace, never merge).
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     *
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (array_key_exists($key, $base) && $this->isMap($base[$key]) && $this->isMap($value)) {
                /** @var array<string, mixed> $baseChild */
                $baseChild = $base[$key];
                /* @var array<string, mixed> $value */
                $base[$key] = $this->deepMerge($baseChild, $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function isMap(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value);
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loadGlobalConfig();
        $this->loadProjectConfig();
        $this->loaded = true;
    }

    private function loadGlobalConfig(): void
    {
        $dir = $this->globalConfigDir();
        if (!is_dir($dir)) {
            return;
        }

        $hostsFile = $dir.'/'.self::HOSTS_FILE;
        if (is_file($hostsFile)) {
            foreach ($this->loader->load($hostsFile) as $name => $hostData) {
                if (is_array($hostData)) {
                    $this->globalHosts[(string) $name] = HostDefinition::fromArray((string) $name, $hostData);
                }
            }
        }

        $defaultsFile = $dir.'/'.self::DEFAULTS_FILE;
        if (is_file($defaultsFile)) {
            $this->globalDefaults = $this->loader->load($defaultsFile);
        }
    }

    private function loadProjectConfig(): void
    {
        $dir = $this->projectConfigDir();
        if (null === $dir) {
            return;
        }

        $defaultsFile = $dir.'/'.self::DEFAULTS_FILE;
        if (is_file($defaultsFile)) {
            $this->projectDefaults = $this->loader->load($defaultsFile);
        }

        foreach (['*.yaml', '*.yml'] as $pattern) {
            foreach (glob($dir.'/'.$pattern) ?: [] as $file) {
                $basename = basename($file);
                if ('defaults.yaml' === $basename || 'defaults.yml' === $basename) {
                    continue;
                }

                $this->loadProjectFile($file);
            }
        }
    }

    private function loadProjectFile(string $file): void
    {
        try {
            $data = $this->loader->load($file);
        } catch (ConfigException) {
            return; // mirror Python: warn-and-continue on a broken project file
        }

        $name = pathinfo($file, \PATHINFO_FILENAME);
        $origin = $data['origin'] ?? null;
        $target = $data['target'] ?? null;

        $this->projectConfigs[$name] = new ProjectConfig(
            name: $name,
            filePath: $file,
            origin: is_string($origin) || is_array($origin) ? $origin : null,
            target: is_string($target) || is_array($target) ? $target : null,
            config: $data,
        );
    }

    private function globalConfigDir(): string
    {
        $home = $this->homeDir ?? (getenv('HOME') ?: sys_get_temp_dir());

        return $home.'/'.self::PROJECT_CONFIG_DIR;
    }

    private function projectConfigDir(): ?string
    {
        $dir = $this->workingDir ?? getcwd();
        if (false === $dir) {
            return null;
        }

        while (true) {
            $candidate = $dir.'/'.self::PROJECT_CONFIG_DIR;
            if (is_dir($candidate)) {
                return $candidate;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }
    }

    private function hostNotFoundMessage(string $name): string
    {
        return sprintf("Host '%s' not found in %s", $name, $this->globalConfigDir().'/'.self::HOSTS_FILE);
    }
}
