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

use Closure;
use KonradMichalik\SyncTool\Config\{FileTransferConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\{LogChannel, SyncMode};
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Remote\Transfer\{TransferPayload, TransferStrategyResolver};

use function rtrim;
use function str_starts_with;

/**
 * FileSync.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class FileSync
{
    public function __construct(
        private TransferStrategyResolver $transferResolver = new TransferStrategyResolver(),
    ) {}

    public function sync(
        SyncConfig $config,
        SyncMode $mode,
        ?Closure $log = null,
        SyncProgress $progress = new NullSyncProgress(),
    ): void {
        $log ??= static function (string $message, LogChannel $channel = LogChannel::Step): void {};

        foreach ($config->files as $entry) {
            $this->transferEntry($config, $mode, $entry, $log, $progress);
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

    private function transferEntry(
        SyncConfig $config,
        SyncMode $mode,
        FileTransferConfig $entry,
        Closure $log,
        SyncProgress $progress,
    ): void {
        $payload = new TransferPayload(
            self::resolvePath($entry->origin, $config->origin->path),
            self::resolvePath($entry->target, $config->target->path),
            $entry->exclude,
            $entry->options ?? $config->filesOptions,
        );

        $strategy = $this->transferResolver->resolve($config, $mode, $log, $progress);
        $log('Transferring files'.$strategy->describe());
        $progress->phase($payload->label());
        $strategy->transfer($config, $payload);
        $progress->advance();
    }
}
