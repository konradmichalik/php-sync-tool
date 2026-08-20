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

use KonradMichalik\SyncTool\Exception\ConfigException;

use function array_keys;
use function array_merge;
use function array_unique;
use function implode;
use function is_array;
use function sort;
use function sprintf;

/**
 * EnvironmentAssembler.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class EnvironmentAssembler
{
    public function __construct(
        private ConfigResolver $resolver,
    ) {}

    /**
     * A configuration with one side taken from the named environment and the
     * other from this machine's `local` block.
     *
     * @return array<string, mixed>
     *
     * @throws ConfigException when the name is unknown or no local block exists
     */
    public function assemble(string $environment, bool $environmentIsOrigin): array
    {
        $local = $this->localEndpoint();

        $base = $this->resolver->getMergedDefaults();
        unset($base['local']);

        $environmentKey = $environmentIsOrigin ? 'origin' : 'target';
        $localKey = $environmentIsOrigin ? 'target' : 'origin';

        $base[$environmentKey] = array_merge($this->asArray($base[$environmentKey] ?? null), $this->endpoint($environment));
        $base[$localKey] = array_merge($this->asArray($base[$localKey] ?? null), $local);

        return $base;
    }

    /**
     * Every name that can be used as an environment.
     *
     * @return list<string>
     */
    public function knownNames(): array
    {
        $names = array_unique(array_merge(
            array_keys($this->resolver->getGlobalHosts()),
            array_keys($this->resolver->getProjectConfigs()),
        ));

        sort($names);

        return $names;
    }

    public function hasLocalEndpoint(): bool
    {
        return [] !== $this->resolver->getLocalEndpoint();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigException
     */
    private function localEndpoint(): array
    {
        $local = $this->resolver->getLocalEndpoint();

        if ([] === $local) {
            throw new ConfigException('No local endpoint configured. Add a "local" block to .sync-tool/defaults.yaml describing this machine, or run "sync-tool init".');
        }

        return $local;
    }

    /**
     * The endpoint behind a name: a host definition first, then the origin block
     * of a project config of that name.
     *
     * @return array<string, mixed>
     *
     * @throws ConfigException
     */
    private function endpoint(string $name): array
    {
        if (isset($this->resolver->getGlobalHosts()[$name])) {
            return $this->resolver->resolveHostLink('@'.$name);
        }

        $projects = $this->resolver->getProjectConfigs();

        if (isset($projects[$name])) {
            $origin = $this->asArray($projects[$name]->config['origin'] ?? null);

            if ([] !== $origin) {
                return $origin;
            }
        }

        $known = $this->knownNames();

        throw new ConfigException(sprintf('Unknown environment "%s". Available: %s', $name, [] === $known ? 'none configured yet, run "sync-tool init"' : implode(', ', $known)));
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
