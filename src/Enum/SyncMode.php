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

namespace MoveElevator\DbSyncTool\Enum;

use function in_array;


/**
 * SyncMode.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

enum SyncMode: string
{
    case DumpLocal = 'DUMP_LOCAL';
    case DumpRemote = 'DUMP_REMOTE';
    case ImportLocal = 'IMPORT_LOCAL';
    case ImportRemote = 'IMPORT_REMOTE';
    case Receiver = 'RECEIVER';
    case Sender = 'SENDER';
    case Proxy = 'PROXY';
    case SyncRemote = 'SYNC_REMOTE';
    case SyncLocal = 'SYNC_LOCAL';

    public function isOriginRemote(): bool
    {
        return in_array($this, [
            self::Receiver,
            self::Proxy,
            self::DumpRemote,
            self::ImportRemote,
            self::SyncRemote,
        ], true);
    }

    public function isTargetRemote(): bool
    {
        return in_array($this, [
            self::Sender,
            self::Proxy,
            self::DumpRemote,
            self::ImportRemote,
            self::SyncRemote,
        ], true);
    }

    public function isImport(): bool
    {
        return self::ImportLocal === $this || self::ImportRemote === $this;
    }

    public function isDump(): bool
    {
        return self::DumpLocal === $this || self::DumpRemote === $this;
    }

    /**
     * Modes that write to the target and are therefore blocked when the
     * target host is protected (mirrors Python check_for_protection).
     */
    public function isProtectable(): bool
    {
        return in_array($this, [
            self::Receiver,
            self::Sender,
            self::Proxy,
            self::SyncLocal,
            self::SyncRemote,
            self::ImportLocal,
            self::ImportRemote,
        ], true);
    }

    public function description(): string
    {
        return match ($this) {
            self::Receiver => '(REMOTE ➔ LOCAL)',
            self::Sender => '(LOCAL ➔ REMOTE)',
            self::Proxy => '(REMOTE ➔ LOCAL ➔ REMOTE)',
            self::DumpLocal => '(LOCAL, ONLY EXPORT)',
            self::DumpRemote => '(REMOTE, ONLY EXPORT)',
            self::ImportLocal => '(REMOTE, ONLY IMPORT)',
            self::ImportRemote => '(LOCAL, ONLY IMPORT)',
            self::SyncLocal => '(LOCAL ➔ LOCAL)',
            self::SyncRemote => '(REMOTE ➔ REMOTE)',
        };
    }
}
