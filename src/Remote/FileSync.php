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

namespace KonradMichalik\SyncTool\Remote;

use KonradMichalik\SyncTool\Config\{FileTransferConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\SyncMode;

use function basename;
use function implode;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;

/**
 * FileSync.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class FileSync
{
    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
    ) {}

    public function sync(SyncConfig $config, SyncMode $mode): void
    {
        foreach ($config->files as $entry) {
            $this->transferEntry($config, $mode, $entry);
        }
    }

    public static function resolvePath(string $path, string $base): string
    {
        if ('' === $path) {
            return $base;
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }
        if ('' === $base) {
            return $path;
        }

        return rtrim($base, '/').'/'.$path;
    }

    public static function excludeArguments(FileTransferConfig $entry): string
    {
        $args = [];
        foreach ($entry->exclude as $pattern) {
            $args[] = sprintf("--exclude='%s'", $pattern);
        }

        return implode(' ', $args);
    }

    public function directCommand(SyncConfig $config, FileTransferConfig $entry): string
    {
        $remoteClient = $config->origin->isRemote() ? $config->origin : $config->target;

        return $this->rsync->build(
            $this->rsync->passwordEnvironment($remoteClient, $config->useSshpass),
            $this->entryOptions($config, $entry),
            $this->rsync->authorization($remoteClient, $config->useSshpass, $remoteClient->jumpHost),
            $this->rsync->userHost($config->origin),
            self::resolvePath($entry->origin, $config->origin->path),
            $this->rsync->userHost($config->target),
            self::resolvePath($entry->target, $config->target->path),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function proxyCommands(SyncConfig $config, FileTransferConfig $entry, string $localTemp): array
    {
        $options = $this->entryOptions($config, $entry);

        $pull = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->origin, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->origin, $config->useSshpass, $config->origin->jumpHost),
            $this->rsync->userHost($config->origin),
            self::resolvePath($entry->origin, $config->origin->path),
            '',
            $localTemp,
        );

        $push = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->target, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->target, $config->useSshpass, $config->target->jumpHost),
            '',
            $localTemp,
            $this->rsync->userHost($config->target),
            self::resolvePath($entry->target, $config->target->path),
        );

        return [$pull, $push];
    }

    private function entryOptions(SyncConfig $config, FileTransferConfig $entry): string
    {
        $extra = self::excludeArguments($entry);
        $perEntry = $entry->options ?? $config->filesOptions;
        if (null !== $perEntry && '' !== $perEntry) {
            $extra = '' === $extra ? $perEntry : $extra.' '.$perEntry;
        }

        return $this->rsync->options('' === $extra ? null : ' '.$extra);
    }

    private function transferEntry(SyncConfig $config, SyncMode $mode, FileTransferConfig $entry): void
    {
        if (SyncMode::Proxy === $mode) {
            $localTemp = sys_get_temp_dir().'/db-sync-files-'.basename(rtrim($entry->target, '/') ?: 'files');
            [$pull, $push] = $this->proxyCommands($config, $entry, $localTemp);
            $local = $this->runners->local();

            try {
                $local->run($pull);
                $local->run($push);
            } finally {
                $local->run(sprintf('rm -rf %s', escapeshellarg($localTemp)), true);
            }

            return;
        }

        if (SyncMode::SyncRemote === $mode) {
            $command = sprintf(
                'rsync %s %s %s',
                $this->entryOptions($config, $entry),
                escapeshellarg(self::resolvePath($entry->origin, $config->origin->path)),
                escapeshellarg(self::resolvePath($entry->target, $config->target->path)),
            );
            $this->runners->forClient($config->origin, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking)->run($command);

            return;
        }

        $this->runners->local()->run($this->directCommand($config, $entry));
    }
}
