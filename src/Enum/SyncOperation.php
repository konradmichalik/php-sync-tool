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

namespace KonradMichalik\SyncTool\Enum;

/**
 * SyncOperation.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum SyncOperation: string
{
    /**
     * Dump, transfer and import.
     */
    case Full = 'FULL';

    /**
     * Dump only, keep the file where it was written.
     */
    case DumpOnly = 'DUMP_ONLY';

    /**
     * Import an existing file, no dump and no transfer.
     */
    case ImportOnly = 'IMPORT_ONLY';
}
