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

namespace MoveElevator\DbSyncTool\Tests\Unit\Mode;

use MoveElevator\DbSyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
use MoveElevator\DbSyncTool\Enum\SyncMode;
use MoveElevator\DbSyncTool\Exception\DbSyncException;
use MoveElevator\DbSyncTool\Mode\SyncModeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * SyncModeResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class SyncModeResolverTest extends TestCase
{
    private SyncModeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new SyncModeResolver();
    }

    #[Test]
    public function receiverWhenOriginRemoteTargetLocal(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'remote.example.com', user: 'u'),
            target: new ClientConfig(path: '/var/www'),
        );

        self::assertSame(SyncMode::Receiver, $this->resolver->resolve($config));
    }

    #[Test]
    public function senderWhenTargetRemoteOriginLocal(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(host: 'remote.example.com', user: 'u'),
        );

        self::assertSame(SyncMode::Sender, $this->resolver->resolve($config));
    }

    #[Test]
    public function proxyWhenBothRemoteDifferentHosts(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'a.example.com', user: 'u'),
            target: new ClientConfig(host: 'b.example.com', user: 'u'),
        );

        self::assertSame(SyncMode::Proxy, $this->resolver->resolve($config));
    }

    #[Test]
    public function dumpRemoteWhenBothRemoteSameHostSameDatabase(): void
    {
        $db = new DatabaseConfig(name: 'db', host: 'h');
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'a.example.com', user: 'u', db: $db),
            target: new ClientConfig(host: 'a.example.com', user: 'u', db: $db),
        );

        self::assertSame(SyncMode::DumpRemote, $this->resolver->resolve($config));
    }

    #[Test]
    public function syncRemoteWhenBothRemoteSameHostDifferentPaths(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/a', host: 'a.example.com', user: 'u'),
            target: new ClientConfig(path: '/b', host: 'a.example.com', user: 'u'),
        );

        self::assertSame(SyncMode::SyncRemote, $this->resolver->resolve($config));
    }

    #[Test]
    public function dumpLocalWhenBothLocalIdentical(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(path: '/var/www'),
        );

        self::assertSame(SyncMode::DumpLocal, $this->resolver->resolve($config));
    }

    #[Test]
    public function syncLocalWhenBothLocalDifferentPaths(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/a'),
            target: new ClientConfig(path: '/b'),
        );

        self::assertSame(SyncMode::SyncLocal, $this->resolver->resolve($config));
    }

    #[Test]
    public function importLocalWhenImportFileAndTargetLocal(): void
    {
        $config = new SyncConfig(
            importFile: '/tmp/dump.sql.gz',
            target: new ClientConfig(path: '/var/www'),
        );

        self::assertSame(SyncMode::ImportLocal, $this->resolver->resolve($config));
    }

    #[Test]
    public function importRemoteWhenImportFileAndTargetRemote(): void
    {
        $config = new SyncConfig(
            importFile: '/tmp/dump.sql.gz',
            target: new ClientConfig(host: 'remote.example.com', user: 'u'),
        );

        self::assertSame(SyncMode::ImportRemote, $this->resolver->resolve($config));
    }

    #[Test]
    public function protectionBlocksWritingModes(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'remote.example.com', user: 'u'),
            target: new ClientConfig(path: '/var/www', protect: true),
        );

        $mode = $this->resolver->resolve($config);

        $this->expectException(DbSyncException::class);
        $this->expectExceptionMessage('protected');

        $this->resolver->checkForProtection($mode, $config);
    }

    #[Test]
    public function protectionAllowsDumpModes(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(path: '/var/www', protect: true),
        );

        $mode = $this->resolver->resolve($config);
        $this->resolver->checkForProtection($mode, $config);

        self::assertSame(SyncMode::DumpLocal, $mode);
    }
}
