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

namespace KonradMichalik\SyncTool\Tests\Fixture;

use KonradMichalik\SyncTool\Enum\{SyncDirection, SyncOperation};
use KonradMichalik\SyncTool\Mode\SyncPlan;

/**
 * Plans.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Plans
{
    public static function receiver(): SyncPlan
    {
        return new SyncPlan(SyncDirection::RemoteToLocal, SyncOperation::Full);
    }

    public static function sender(): SyncPlan
    {
        return new SyncPlan(SyncDirection::LocalToRemote, SyncOperation::Full);
    }

    public static function proxy(): SyncPlan
    {
        return new SyncPlan(SyncDirection::RemoteToRemote, SyncOperation::Full);
    }

    public static function remoteCopy(): SyncPlan
    {
        return new SyncPlan(SyncDirection::RemoteToRemote, SyncOperation::Full, sameHost: true);
    }

    public static function syncLocal(): SyncPlan
    {
        return new SyncPlan(SyncDirection::LocalToLocal, SyncOperation::Full, sameHost: true);
    }

    public static function dumpLocal(): SyncPlan
    {
        return new SyncPlan(SyncDirection::LocalToLocal, SyncOperation::DumpOnly, sameHost: true);
    }

    public static function dumpRemote(): SyncPlan
    {
        return new SyncPlan(SyncDirection::RemoteToRemote, SyncOperation::DumpOnly, sameHost: true);
    }

    public static function importLocal(): SyncPlan
    {
        return new SyncPlan(SyncDirection::LocalToLocal, SyncOperation::ImportOnly, sameHost: true);
    }

    public static function importRemote(): SyncPlan
    {
        return new SyncPlan(SyncDirection::LocalToRemote, SyncOperation::ImportOnly);
    }
}
