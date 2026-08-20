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

namespace KonradMichalik\SyncTool\Tests\Unit\Mode;

use KonradMichalik\SyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Mode\SyncModeResolver;
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

        self::assertSame('RECEIVER', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function senderWhenTargetRemoteOriginLocal(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(host: 'remote.example.com', user: 'u'),
        );

        self::assertSame('SENDER', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function proxyWhenBothRemoteDifferentHosts(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'a.example.com', user: 'u'),
            target: new ClientConfig(host: 'b.example.com', user: 'u'),
        );

        self::assertSame('PROXY', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function dumpRemoteWhenBothRemoteSameHostSameDatabase(): void
    {
        $db = new DatabaseConfig(name: 'db', host: 'h');
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'a.example.com', user: 'u', db: $db),
            target: new ClientConfig(host: 'a.example.com', user: 'u', db: $db),
        );

        self::assertSame('DUMP_REMOTE', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function syncRemoteWhenBothRemoteSameHostDifferentPaths(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/a', host: 'a.example.com', user: 'u'),
            target: new ClientConfig(path: '/b', host: 'a.example.com', user: 'u'),
        );

        self::assertSame('SYNC_REMOTE', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function syncRemoteWhenBothRemoteSameHostDifferentDatabase(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'a.example.com', user: 'u', db: new DatabaseConfig(name: 'db_a', host: 'h')),
            target: new ClientConfig(host: 'a.example.com', user: 'u', db: new DatabaseConfig(name: 'db_b', host: 'h')),
        );

        self::assertSame('SYNC_REMOTE', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function dumpLocalWhenBothLocalIdentical(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(path: '/var/www'),
        );

        self::assertSame('DUMP_LOCAL', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function syncLocalWhenBothLocalDifferentPaths(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/a'),
            target: new ClientConfig(path: '/b'),
        );

        self::assertSame('SYNC_LOCAL', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function importLocalWhenImportFileAndTargetLocal(): void
    {
        $config = new SyncConfig(
            importFile: '/tmp/dump.sql.gz',
            target: new ClientConfig(path: '/var/www'),
        );

        self::assertSame('IMPORT_LOCAL', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function importRemoteWhenImportFileAndTargetRemote(): void
    {
        $config = new SyncConfig(
            importFile: '/tmp/dump.sql.gz',
            target: new ClientConfig(host: 'remote.example.com', user: 'u'),
        );

        self::assertSame('IMPORT_REMOTE', $this->resolver->resolve($config)->label());
    }

    #[Test]
    public function protectionBlocksWritingModes(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(host: 'remote.example.com', user: 'u'),
            target: new ClientConfig(path: '/var/www', protect: true),
        );

        $plan = $this->resolver->resolve($config);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('protected');

        $this->resolver->checkForProtection($plan, $config);
    }

    #[Test]
    public function protectionAllowsDumpModes(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www'),
            target: new ClientConfig(path: '/var/www', protect: true),
        );

        $plan = $this->resolver->resolve($config);
        $this->resolver->checkForProtection($plan, $config);

        self::assertSame('DUMP_LOCAL', $plan->label());
    }
}
