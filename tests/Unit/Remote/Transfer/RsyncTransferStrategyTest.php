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
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\Transfer\{RsyncTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner, RecordingProgress};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RsyncTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncTransferStrategyTest extends TestCase
{
    #[Test]
    public function transfersDumpFromRemoteOriginToLocalTarget(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/tmp/o.gz', '/tmp/t.gz'),
        );

        self::assertTrue($recorder->ran('rsync'));
        self::assertTrue($recorder->ran('deploy@o.example.com:/tmp/o.gz'));
        self::assertTrue($recorder->ran('/tmp/t.gz'));
    }

    #[Test]
    public function appliesExcludePatternsAndExtraOptions(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin', '/var/www/fileadmin', ['*.log'], '--archive'),
        );

        self::assertTrue($recorder->ran("--exclude='*.log'"));
        self::assertTrue($recorder->ran('--archive'));
    }

    #[Test]
    public function describeIsEmpty(): void
    {
        self::assertSame('', (new RsyncTransferStrategy())->describe());
    }

    #[Test]
    public function logsTheSanitizedCommandBeforeRunningIt(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
        $recorder = new RecordingCommandRunner();
        $logs = [];

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder), log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        }))->transfer($config, new TransferPayload('/tmp/o.gz', '/tmp/t.gz'));

        self::assertNotEmpty($logs);
        self::assertStringContainsString('rsync', $logs[0]);
        self::assertStringContainsString('/tmp/o.gz', $logs[0]);
    }

    #[Test]
    public function rendersABarFedByTheRsyncPercentageWhenRsyncSupportsIt(): void
    {
        $recorder = new RecordingCommandRunner(
            ['rsync --version' => 'rsync  version 3.2.7  protocol version 31'],
            streams: ['--info=progress2' => "  1,000  10%  1MB/s\r  4,500  45%  2MB/s\r"],
        );
        $progress = new RecordingProgress();

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder), progress: $progress))->transfer(
            $this->config(),
            new TransferPayload('/tmp/o.gz', '/tmp/t.gz'),
        );

        self::assertTrue($recorder->ran('--info=progress2'));
        self::assertSame([45.0], $progress->percents);
        self::assertCount(1, $progress->bars);
        self::assertCount(1, $progress->succeeded);
    }

    #[Test]
    public function fallsBackToASpinnerWhenRsyncIsTooOldForProgressOutput(): void
    {
        $recorder = new RecordingCommandRunner(['rsync --version' => 'rsync  version 2.6.9  protocol version 29']);
        $progress = new RecordingProgress();

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder), progress: $progress))->transfer(
            $this->config(),
            new TransferPayload('/tmp/o.gz', '/tmp/t.gz'),
        );

        self::assertFalse($recorder->ran('--info=progress2'));
        self::assertSame([], $progress->bars);
        self::assertCount(1, $progress->spinners);
        self::assertCount(1, $progress->succeeded);
    }

    #[Test]
    public function marksTheProgressAsFailedWhenRsyncFails(): void
    {
        $recorder = new RecordingCommandRunner(
            ['rsync --version' => 'rsync  version 3.2.7  protocol version 31'],
            throwOn: '--info=progress2',
        );
        $progress = new RecordingProgress();

        try {
            (new RsyncTransferStrategy(new FakeRunnerFactory($recorder), progress: $progress))->transfer(
                $this->config(),
                new TransferPayload('/tmp/o.gz', '/tmp/t.gz'),
            );
            self::fail('Expected the failing rsync run to bubble up.');
        } catch (SyncException) {
            self::assertCount(1, $progress->failed);
            self::assertSame([], $progress->succeeded);
        }
    }

    #[Test]
    public function doesNotProbeTheRsyncVersionWithoutAProgressDisplay(): void
    {
        $recorder = new RecordingCommandRunner();

        (new RsyncTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $this->config(),
            new TransferPayload('/tmp/o.gz', '/tmp/t.gz'),
        );

        self::assertFalse($recorder->ran('rsync --version'));
        self::assertFalse($recorder->ran('--info=progress2'));
    }

    private function config(): SyncConfig
    {
        return SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
    }
}
