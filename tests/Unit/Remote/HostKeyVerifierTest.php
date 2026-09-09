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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote;

use KonradMichalik\SyncTool\Enum\HostKeyCheckingMode;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\{HostKeyStatus, HostKeyVerifier};
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
        (new HostKeyVerifier())->assert(HostKeyStatus::Matched, HostKeyCheckingMode::Strict, 'h');
    }

    #[Test]
    public function mismatchThrowsWhenStrict(): void
    {
        $this->expectException(SyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Mismatch, HostKeyCheckingMode::Strict, 'h');
    }

    #[Test]
    public function mismatchThrowsWhenAcceptNew(): void
    {
        $this->expectException(SyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Mismatch, HostKeyCheckingMode::AcceptNew, 'h');
    }

    #[Test]
    public function mismatchThrowsWhenOff(): void
    {
        $this->expectException(SyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Mismatch, HostKeyCheckingMode::Off, 'h');
    }

    #[Test]
    public function unknownThrowsWhenStrict(): void
    {
        $this->expectException(SyncException::class);
        (new HostKeyVerifier())->assert(HostKeyStatus::Unknown, HostKeyCheckingMode::Strict, 'h');
    }

    #[Test]
    public function unknownPassesWhenOff(): void
    {
        $this->expectNotToPerformAssertions();
        (new HostKeyVerifier())->assert(HostKeyStatus::Unknown, HostKeyCheckingMode::Off, 'h');
    }

    #[Test]
    public function unknownPassesWhenAcceptNew(): void
    {
        $this->expectNotToPerformAssertions();
        (new HostKeyVerifier())->assert(HostKeyStatus::Unknown, HostKeyCheckingMode::AcceptNew, 'h');
    }

    #[Test]
    public function shouldTrustOnFirstUseOnlyForUnknownAndAcceptNew(): void
    {
        $verifier = new HostKeyVerifier();

        self::assertTrue($verifier->shouldTrustOnFirstUse(HostKeyStatus::Unknown, HostKeyCheckingMode::AcceptNew));

        self::assertFalse($verifier->shouldTrustOnFirstUse(HostKeyStatus::Unknown, HostKeyCheckingMode::Strict));
        self::assertFalse($verifier->shouldTrustOnFirstUse(HostKeyStatus::Unknown, HostKeyCheckingMode::Off));
        self::assertFalse($verifier->shouldTrustOnFirstUse(HostKeyStatus::Matched, HostKeyCheckingMode::AcceptNew));
        self::assertFalse($verifier->shouldTrustOnFirstUse(HostKeyStatus::Mismatch, HostKeyCheckingMode::AcceptNew));
    }
}
