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

namespace KonradMichalik\SyncTool\Backup;

use DateTimeImmutable;
use KonradMichalik\SyncTool\Config\SyncConfig;

/**
 * DumpFileNamer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DumpFileNamer
{
    /**
     * Marks a dump as ours. Retention globs on this prefix, so a dump directory
     * shared with other tools (and `/tmp/`, the default, always is) keeps its
     * foreign `.sql` and `.gz` files.
     */
    public const PREFIX = 'sync-tool_';

    /**
     * Seconds are part of the stamp because two runs within the same minute
     * would otherwise write, transfer and prune the same filename.
     */
    public function generate(SyncConfig $config, ?DateTimeImmutable $now = null): string
    {
        if ('' !== $config->dumpName) {
            return $config->dumpName.'.sql';
        }

        return self::PREFIX.$config->origin->db->name.'_'.self::stamp($now).'.sql';
    }

    /**
     * The safety copy of the target, named so that it is recognizable next to the
     * dump being imported and still covered by retention.
     */
    public function generateBackup(SyncConfig $config, ?DateTimeImmutable $now = null): string
    {
        return self::PREFIX.'backup_'.$config->target->db->name.'_'.self::stamp($now).'.sql';
    }

    private static function stamp(?DateTimeImmutable $now): string
    {
        return ($now ?? new DateTimeImmutable())->format('Y-m-d_H-i-s');
    }
}
