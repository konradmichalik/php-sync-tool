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
use KonradMichalik\SyncTool\Remote\Transfer\{RemoteCopyTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner, RecordingProgress};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RemoteCopyTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RemoteCopyTransferStrategyTest extends TestCase
{
    #[Test]
    public function copiesBetweenTwoPathsOnTheSameRemoteHostViaOriginRunner(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();

        (new RemoteCopyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin', '/srv/web/fileadmin', [], '--archive'),
        );

        self::assertTrue($recorder->ran('rsync'));
        self::assertTrue($recorder->ran('/srv/app/fileadmin'));
        self::assertTrue($recorder->ran('/srv/web/fileadmin'));
        self::assertTrue($recorder->ran('--archive'));
        self::assertFalse($recorder->ran('@'), 'no user@host prefix — both paths live on the same host');
    }

    #[Test]
    public function describeMentionsRemoteHost(): void
    {
        self::assertSame(' on the remote host', (new RemoteCopyTransferStrategy())->describe());
    }

    #[Test]
    public function logsTheSanitizedCommandBeforeRunningIt(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();
        $logs = [];

        (new RemoteCopyTransferStrategy(new FakeRunnerFactory($recorder), log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        }))->transfer($config, new TransferPayload('/srv/app/fileadmin', '/srv/web/fileadmin'));

        self::assertNotEmpty($logs);
        self::assertStringContainsString('rsync', $logs[0]);
        self::assertStringContainsString('/srv/app/fileadmin', $logs[0]);
    }
    #[Test]
    public function reportsTheRemoteCopyAsASpinner(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'h.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 'h.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $progress = new RecordingProgress();

        (new RemoteCopyTransferStrategy(new FakeRunnerFactory(new RecordingCommandRunner()), progress: $progress))->transfer(
            $config,
            new TransferPayload('/srv/app/dump.gz', '/srv/web/dump.gz'),
        );

        self::assertSame(['Transferring dump.gz'], $progress->spinners);
        self::assertSame(['Transferring dump.gz'], $progress->succeeded);
    }
}
