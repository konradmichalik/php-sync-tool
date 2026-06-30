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

namespace MoveElevator\DbSyncTool\Backup;

use DateTimeImmutable;
use MoveElevator\DbSyncTool\Config\SyncConfig;

/**
 * DumpFileNamer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DumpFileNamer
{
    public function generate(SyncConfig $config, ?DateTimeImmutable $now = null): string
    {
        if ('' !== $config->dumpName) {
            return $config->dumpName.'.sql';
        }

        $timestamp = ($now ?? new DateTimeImmutable())->format('Y-m-d_H-i');

        return '_'.$config->origin->db->name.'_'.$timestamp.'.sql';
    }
}
