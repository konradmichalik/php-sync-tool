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
use KonradMichalik\SyncTool\Remote\Transfer\{RsyncTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
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
}
