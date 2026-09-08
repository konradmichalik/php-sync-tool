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
use KonradMichalik\SyncTool\Enum\LogChannel;
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Remote\Transfer\{TransferPayload, TransferStrategyResolver};

use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function substr;

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
        SyncPlan $plan,
        ?Closure $log = null,
        SyncProgress $progress = new NullSyncProgress(),
    ): void {
        $log ??= static function (string $message, LogChannel $channel = LogChannel::Step): void {};

        foreach ($config->files as $entry) {
            $this->transferEntry($config, $plan, $entry, $log, $progress);
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

    /**
     * Legacy file-sync-tool configs commonly used a shell glob (`fileadmin/*`)
     * to sync a directory's contents, relying on the *local* shell to expand
     * it before invoking rsync. RsyncCommandBuilder quotes the whole path as
     * one shell argument (closing a command-injection hole), so the shell
     * never sees it and rsync receives a literal, nonexistent `*` entry
     * instead. A trailing `/` already makes rsync sync the directory's
     * contents, so a trailing `/*` is rewritten to `/` rather than left to
     * fail.
     */
    private static function normalizeGlobSuffix(string $path): string
    {
        return str_ends_with($path, '/*') ? substr($path, 0, -1) : $path;
    }

    private function transferEntry(
        SyncConfig $config,
        SyncPlan $plan,
        FileTransferConfig $entry,
        Closure $log,
        SyncProgress $progress,
    ): void {
        $payload = new TransferPayload(
            self::normalizeGlobSuffix(self::resolvePath($entry->origin, $config->origin->path)),
            self::normalizeGlobSuffix(self::resolvePath($entry->target, $config->target->path)),
            $entry->exclude,
            $entry->options ?? $config->filesOptions,
        );

        $strategy = $this->transferResolver->resolve($config, $plan, $log, $progress);
        $log('Transferring files'.$strategy->describe());
        $progress->phase($payload->label());
        $strategy->transfer($config, $payload);
        $progress->advance();
    }
}
