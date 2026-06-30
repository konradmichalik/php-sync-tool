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

namespace MoveElevator\DbSyncTool\Tests\Unit\Remote;

use MoveElevator\DbSyncTool\Exception\DbSyncException;
use MoveElevator\DbSyncTool\Remote\{SftpDirection, SftpTransfer};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SftpTransferTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SftpTransferTest extends TestCase
{
    #[Test]
    public function downloadWhenOriginRemote(): void
    {
        self::assertSame(SftpDirection::Download, SftpTransfer::direction(true, false));
    }

    #[Test]
    public function uploadWhenTargetRemote(): void
    {
        self::assertSame(SftpDirection::Upload, SftpTransfer::direction(false, true));
    }

    #[Test]
    public function remoteToRemoteIsRejected(): void
    {
        $this->expectException(DbSyncException::class);
        SftpTransfer::direction(true, true);
    }
}
