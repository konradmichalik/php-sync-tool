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
use KonradMichalik\SyncTool\Enum\LogChannel;
use KonradMichalik\SyncTool\Remote\Transfer\{LocalCopyTransferStrategy, ProxyTransferStrategy, RemoteCopyTransferStrategy, RsyncTransferStrategy, SftpTransferStrategy, TransferPayload, TransferStrategyResolver};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, Plans, RecordingCommandRunner};
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

    /**
     * Through a fake runner, so the choice of strategy is decided by the fixture
     * rather than by whether the machine running the suite happens to have rsync.
     */
    protected function setUp(): void
    {
        $this->resolver = new TransferStrategyResolver(new FakeRunnerFactory(new RecordingCommandRunner()));
    }

    #[Test]
    public function bothLocalUsesRsyncWhenItIsAvailable(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/srv/a', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RsyncTransferStrategy::class, $this->resolver->resolve($config, Plans::syncLocal()));
    }

    /**
     * SFTP is no fallback between two local paths: there is no host to reach. The
     * flag used to be ignored here, so `--no-rsync` still ran rsync.
     */
    #[Test]
    public function bothLocalCopiesTheFileWhenRsyncIsNotWanted(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['path' => '/srv/a', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(LocalCopyTransferStrategy::class, $this->resolver->resolve($config, Plans::syncLocal()));
    }

    #[Test]
    public function aMissingRsyncFallsBackToSftpAndSaysSo(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $warnings = [];
        $resolver = new TransferStrategyResolver(new FakeRunnerFactory($this->withoutRsync()));

        $strategy = $resolver->resolve($config, Plans::receiver(), static function (string $message, LogChannel $channel = LogChannel::Step) use (&$warnings): void {
            if (LogChannel::Warning === $channel) {
                $warnings[] = $message;
            }
        });

        self::assertInstanceOf(SftpTransferStrategy::class, $strategy);
        self::assertSame(['rsync not found, falling back to a transfer without it'], $warnings);
    }

    #[Test]
    public function aMissingRsyncCopiesTheFileBetweenTwoLocalPaths(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/srv/a', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $resolver = new TransferStrategyResolver(new FakeRunnerFactory($this->withoutRsync()));

        self::assertInstanceOf(LocalCopyTransferStrategy::class, $resolver->resolve($config, Plans::syncLocal()));
    }

    /**
     * An explicit `--no-rsync` already picked the fallback, so there is nothing to
     * warn about and no reason to spawn the probe.
     */
    #[Test]
    public function noWarningWhenRsyncWasTurnedOffDeliberately(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = $this->withoutRsync();
        $messages = [];

        (new TransferStrategyResolver(new FakeRunnerFactory($recorder)))->resolve($config, Plans::receiver(), static function (string $message, LogChannel $channel = LogChannel::Step) use (&$messages): void {
            $messages[] = $channel;
        });

        self::assertNotContains(LogChannel::Warning, $messages);
        self::assertFalse($recorder->ran('rsync --version'), 'no probe when the answer cannot change the outcome');
    }

    #[Test]
    public function noRsyncFlagSelectsSftpWhenExactlyOneSideIsRemote(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, Plans::receiver()));
    }

    #[Test]
    public function proxyModeSelectsProxyStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(ProxyTransferStrategy::class, $this->resolver->resolve($config, Plans::proxy()));
    }

    #[Test]
    public function syncRemoteModeSelectsRemoteCopyStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RemoteCopyTransferStrategy::class, $this->resolver->resolve($config, Plans::remoteCopy()));
    }

    #[Test]
    public function receiverModeDefaultsToRsyncStrategy(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/srv/b', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(RsyncTransferStrategy::class, $this->resolver->resolve($config, Plans::receiver()));
    }

    #[Test]
    public function noRsyncFlagOutranksProxyAndSyncRemoteModes(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync' => false,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, Plans::proxy()));
        self::assertInstanceOf(SftpTransferStrategy::class, $this->resolver->resolve($config, Plans::remoteCopy()));
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

        $strategy = $resolver->resolve($config, Plans::receiver(), static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });
        $strategy->transfer($config, new TransferPayload('/tmp/o.gz', '/tmp/t.gz'));

        self::assertInstanceOf(RsyncTransferStrategy::class, $strategy);
        self::assertNotEmpty($logs, 'the log closure passed to resolve() reached the resolved strategy');
    }

    private function withoutRsync(): RecordingCommandRunner
    {
        return new RecordingCommandRunner(['rsync --version' => '']);
    }
}
