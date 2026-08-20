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
 * SyncDirection.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum SyncDirection: string
{
    case RemoteToLocal = 'REMOTE_TO_LOCAL';
    case LocalToRemote = 'LOCAL_TO_REMOTE';
    case RemoteToRemote = 'REMOTE_TO_REMOTE';
    case LocalToLocal = 'LOCAL_TO_LOCAL';

    public static function between(bool $originRemote, bool $targetRemote): self
    {
        return match (true) {
            $originRemote && $targetRemote => self::RemoteToRemote,
            $originRemote => self::RemoteToLocal,
            $targetRemote => self::LocalToRemote,
            default => self::LocalToLocal,
        };
    }

    public function originRemote(): bool
    {
        return self::RemoteToLocal === $this || self::RemoteToRemote === $this;
    }

    public function targetRemote(): bool
    {
        return self::LocalToRemote === $this || self::RemoteToRemote === $this;
    }
}
