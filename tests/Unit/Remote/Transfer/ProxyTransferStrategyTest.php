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
use KonradMichalik\SyncTool\Remote\Transfer\{ProxyTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ProxyTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ProxyTransferStrategyTest extends TestCase
{
    #[Test]
    public function pullsFromOriginThenPushesToTargetViaLocalTemp(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/o/dump.gz', '/t/dump.gz'),
        );

        self::assertTrue($recorder->ran('deploy@o.example.com:/o/dump.gz'), 'pulls from origin');
        self::assertTrue($recorder->ran('deploy@t.example.com:/t/dump.gz'), 'pushes to target');
        self::assertTrue($recorder->ran('php-sync-tool-dump.gz'), 'uses a local temp path derived from the target basename');
        self::assertTrue($recorder->ran('rm -rf'), 'cleans up the local temp path');
    }

    #[Test]
    public function worksForDirectoryEntriesToo(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin', '/srv/web/fileadmin', ['*.log']),
        );

        self::assertTrue($recorder->ran('php-sync-tool-fileadmin'));
        self::assertTrue($recorder->ran("--exclude='*.log'"));
    }

    #[Test]
    public function describeMentionsProxy(): void
    {
        self::assertSame(' via proxy (origin → local → target)', (new ProxyTransferStrategy())->describe());
    }

    #[Test]
    public function logsBothPullAndPushCommandsBeforeRunningThem(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();
        $logs = [];

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder), log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        }))->transfer($config, new TransferPayload('/o/dump.gz', '/t/dump.gz'));

        self::assertCount(2, $logs, 'logs once per leg (pull, push)');
        self::assertStringContainsString('deploy@o.example.com:/o/dump.gz', $logs[0]);
        self::assertStringContainsString('deploy@t.example.com:/t/dump.gz', $logs[1]);
    }
}
