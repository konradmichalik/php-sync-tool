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

use KonradMichalik\SyncTool\Remote\RsyncVersion;
use KonradMichalik\SyncTool\Tests\Fixture\RecordingCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RsyncVersionTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncVersionTest extends TestCase
{
    #[Test]
    public function reportsProgressSupportForRsync31AndNewer(): void
    {
        $runner = new RecordingCommandRunner([
            'rsync --version' => 'rsync  version 3.2.7  protocol version 31',
        ]);

        self::assertTrue((new RsyncVersion())->supportsProgress2($runner));
        self::assertSame(['rsync --version'], $runner->commands);
    }

    #[Test]
    public function deniesProgressSupportForTheRsyncShippedByMacos(): void
    {
        $runner = new RecordingCommandRunner([
            'rsync --version' => 'rsync  version 2.6.9  protocol version 29',
        ]);

        self::assertFalse((new RsyncVersion())->supportsProgress2($runner));
    }

    #[Test]
    public function deniesProgressSupportWhenTheVersionCannotBeRead(): void
    {
        self::assertFalse((new RsyncVersion())->supportsProgress2(new RecordingCommandRunner()));
    }

    #[Test]
    public function asksTheRunnerOnlyOnce(): void
    {
        $runner = new RecordingCommandRunner([
            'rsync --version' => 'rsync  version 3.4.1  protocol version 32',
        ]);
        $version = new RsyncVersion();

        $version->supportsProgress2($runner);
        $version->supportsProgress2($runner);

        self::assertSame(['rsync --version'], $runner->commands);
    }
}
