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
use MoveElevator\DbSyncTool\Remote\{HostKeyStatus, HostKeyVerifier};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HostKeyVerifierTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class HostKeyVerifierTest extends TestCase
{
    #[Test]
    public function matchedNeverThrows(): void
    {
        $this->expectNotToPerformAssertions();
        (new HostKeyVerifier())->assert(HostKeyStatus::Matched, true, 'h');
    }

    #[Test]
    public function mismatchThrowsEvenWhenNotStrict(): void
    {
        $this->expectException(DbSyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Mismatch, false, 'h');
    }

    #[Test]
    public function unknownThrowsWhenStrict(): void
    {
        $this->expectException(DbSyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Unknown, true, 'h');
    }

    #[Test]
    public function unknownPassesWhenNotStrict(): void
    {
        $this->expectNotToPerformAssertions();
        (new HostKeyVerifier())->assert(HostKeyStatus::Unknown, false, 'h');
    }
}
