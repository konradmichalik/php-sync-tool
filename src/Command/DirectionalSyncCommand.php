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

namespace KonradMichalik\SyncTool\Command;

use KonradMichalik\SyncTool\Exception\ConfigException;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_merge;
use function array_unique;
use function implode;
use function sort;
use function sprintf;

/**
 * DirectionalSyncCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
abstract class DirectionalSyncCommand extends SyncCommand
{
    // One named environment plus this machine: the verb says which side is which.
    protected function configure(): void
    {
        $this->addArgument(
            'environment',
            InputArgument::REQUIRED,
            'Name of the environment: a host from the host file or a project config',
        );

        $this->configureSharedOptions();
    }

    /**
     * Whether the named environment is the side data comes from.
     */
    abstract protected function environmentIsOrigin(): bool;

    protected function buildConfig(InputInterface $input, OutputInterface $output): array
    {
        /** @var string $environment */
        $environment = $input->getArgument('environment');

        $local = $this->resolver->getLocalEndpoint();

        if ([] === $local) {
            throw new ConfigException('No local endpoint configured. Add a "local" block to .sync-tool/defaults.yaml describing this machine, or run "sync-tool init".');
        }

        $base = $this->resolver->getMergedDefaults();
        unset($base['local']);

        $environmentKey = $this->environmentIsOrigin() ? 'origin' : 'target';
        $localKey = $this->environmentIsOrigin() ? 'target' : 'origin';

        $base[$environmentKey] = array_merge(
            $this->asArray($base[$environmentKey] ?? null),
            $this->environmentEndpoint($environment),
        );
        $base[$localKey] = array_merge($this->asArray($base[$localKey] ?? null), $local);

        return $this->finishConfig($base, $input, $output);
    }

    /**
     * The endpoint behind a name: a host definition first, then the origin block
     * of a project config of that name.
     *
     * @return array<string, mixed>
     */
    private function environmentEndpoint(string $name): array
    {
        $hosts = $this->resolver->getGlobalHosts();

        if (isset($hosts[$name])) {
            return $this->resolver->resolveHostLink('@'.$name);
        }

        $projects = $this->resolver->getProjectConfigs();

        if (isset($projects[$name])) {
            $origin = $projects[$name]->config['origin'] ?? null;

            if ([] !== $this->asArray($origin)) {
                return $this->asArray($origin);
            }
        }

        throw new ConfigException(sprintf('Unknown environment "%s". Available: %s', $name, $this->knownEnvironments()));
    }

    private function knownEnvironments(): string
    {
        $names = array_unique(array_merge(
            array_keys($this->resolver->getGlobalHosts()),
            array_keys($this->resolver->getProjectConfigs()),
        ));

        if ([] === $names) {
            return 'none configured yet, run "sync-tool init"';
        }

        sort($names);

        return implode(', ', $names);
    }
}
