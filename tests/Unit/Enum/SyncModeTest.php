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

namespace KonradMichalik\SyncTool\Tests\Unit\Enum;

use KonradMichalik\SyncTool\Enum\SyncMode;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * SyncModeTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncModeTest extends TestCase
{
    #[Test]
    #[DataProvider('originRemoteProvider')]
    public function isOriginRemote(SyncMode $mode, bool $expected): void
    {
        self::assertSame($expected, $mode->isOriginRemote());
    }

    /**
     * @return iterable<string, array{SyncMode, bool}>
     */
    public static function originRemoteProvider(): iterable
    {
        yield 'receiver' => [SyncMode::Receiver, true];
        yield 'proxy' => [SyncMode::Proxy, true];
        yield 'dump remote' => [SyncMode::DumpRemote, true];
        yield 'import remote' => [SyncMode::ImportRemote, true];
        yield 'sync remote' => [SyncMode::SyncRemote, true];
        yield 'sender' => [SyncMode::Sender, false];
        yield 'dump local' => [SyncMode::DumpLocal, false];
        yield 'import local' => [SyncMode::ImportLocal, false];
        yield 'sync local' => [SyncMode::SyncLocal, false];
    }

    #[Test]
    #[DataProvider('targetRemoteProvider')]
    public function isTargetRemote(SyncMode $mode, bool $expected): void
    {
        self::assertSame($expected, $mode->isTargetRemote());
    }

    /**
     * @return iterable<string, array{SyncMode, bool}>
     */
    public static function targetRemoteProvider(): iterable
    {
        yield 'sender' => [SyncMode::Sender, true];
        yield 'proxy' => [SyncMode::Proxy, true];
        yield 'dump remote' => [SyncMode::DumpRemote, true];
        yield 'import remote' => [SyncMode::ImportRemote, true];
        yield 'sync remote' => [SyncMode::SyncRemote, true];
        yield 'receiver' => [SyncMode::Receiver, false];
        yield 'import local' => [SyncMode::ImportLocal, false];
    }

    #[Test]
    public function isImportAndIsDump(): void
    {
        self::assertTrue(SyncMode::ImportLocal->isImport());
        self::assertTrue(SyncMode::ImportRemote->isImport());
        self::assertFalse(SyncMode::Receiver->isImport());

        self::assertTrue(SyncMode::DumpLocal->isDump());
        self::assertTrue(SyncMode::DumpRemote->isDump());
        self::assertFalse(SyncMode::Receiver->isDump());
    }

    #[Test]
    public function isProtectableForWritingModesOnly(): void
    {
        self::assertTrue(SyncMode::Receiver->isProtectable());
        self::assertTrue(SyncMode::ImportLocal->isProtectable());
        self::assertFalse(SyncMode::DumpLocal->isProtectable());
        self::assertFalse(SyncMode::DumpRemote->isProtectable());
    }

    #[Test]
    public function descriptionIsDefinedForEveryMode(): void
    {
        foreach (SyncMode::cases() as $mode) {
            self::assertNotSame('', $mode->description());
        }
    }
}
