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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\SyncMode;
use KonradMichalik\SyncTool\Remote\Transfer\{ProxyTransferStrategy, RemoteCopyTransferStrategy, RsyncTransferStrategy, SftpTransferStrategy, TransferPayload, TransferStrategyResolver};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TransferStrategyResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class TransferStrategyResolverTest extends TestCase
{
    private TransferStrategyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TransferStrategyResolver();
    }

    #[Test]
    public function bothLocalAlwaysUsesRsyncRegardlessOfNoRsyncFlag(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['path' => '/srv/a', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RsyncTransferStrategy::class, $this->resolver->resolve($config, SyncMode::SyncLocal));
    }

    #[Test]
    public function noRsyncFlagSelectsSftpWhenExactlyOneSideIsRemote(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, SyncMode::Receiver));
    }

    #[Test]
    public function proxyModeSelectsProxyStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(ProxyTransferStrategy::class, $this->resolver->resolve($config, SyncMode::Proxy));
    }

    #[Test]
    public function syncRemoteModeSelectsRemoteCopyStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RemoteCopyTransferStrategy::class, $this->resolver->resolve($config, SyncMode::SyncRemote));
    }

    #[Test]
    public function receiverModeDefaultsToRsyncStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RsyncTransferStrategy::class, $this->resolver->resolve($config, SyncMode::Receiver));
    }

    #[Test]
    public function noRsyncFlagOutranksProxyAndSyncRemoteModes(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, SyncMode::Proxy));
        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, SyncMode::SyncRemote));
    }

    #[Test]
    public function optionalLogClosurePassedToResolveReachesTheReturnedStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();
        $resolver = new TransferStrategyResolver(new FakeRunnerFactory($recorder));
        $logs = [];

        $strategy = $resolver->resolve($config, SyncMode::Receiver, static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });
        $strategy->transfer($config, new TransferPayload('/tmp/o.gz', '/tmp/t.gz'));

        self::assertInstanceOf(RsyncTransferStrategy::class, $strategy);
        self::assertNotEmpty($logs, 'the log closure passed to resolve() reached the resolved strategy');
    }
}
