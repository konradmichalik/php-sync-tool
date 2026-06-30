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

namespace MoveElevator\DbSyncTool\Tests\Unit\Remote;

use MoveElevator\DbSyncTool\Config\{FileTransferConfig, SyncConfig};
use MoveElevator\DbSyncTool\Remote\FileSync;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSyncTest extends TestCase
{
    #[Test]
    public function resolvePathJoinsRelativeAndKeepsAbsolute(): void
    {
        self::assertSame('/srv/app/fileadmin', FileSync::resolvePath('fileadmin', '/srv/app'));
        self::assertSame('/srv/app/fileadmin', FileSync::resolvePath('fileadmin', '/srv/app/'));
        self::assertSame('/abs/path', FileSync::resolvePath('/abs/path', '/srv/app'));
        self::assertSame('/srv/app', FileSync::resolvePath('', '/srv/app'));
    }

    #[Test]
    public function excludeArgumentsRenderPatterns(): void
    {
        self::assertSame('', FileSync::excludeArguments(new FileTransferConfig()));
        self::assertSame(
            "--exclude='*.log' --exclude='cache/'",
            FileSync::excludeArguments(new FileTransferConfig(exclude: ['*.log', 'cache/'])),
        );
    }

    #[Test]
    public function directCommandPullsForReceiver(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin', 'exclude' => ['*.log']]],
        ]);

        $cmd = (new FileSync())->directCommand($config, $config->files[0]);

        self::assertStringContainsString('deploy@o.example.com:/srv/app/fileadmin', $cmd);
        self::assertStringContainsString('/var/www/fileadmin', $cmd);
        self::assertStringContainsString("--exclude='*.log'", $cmd);
    }

    #[Test]
    public function directCommandPushesForSender(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/var/www', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $cmd = (new FileSync())->directCommand($config, $config->files[0]);

        self::assertStringContainsString('/var/www/fileadmin', $cmd);
        self::assertStringContainsString('deploy@t.example.com:/srv/app/fileadmin', $cmd);
    }

    #[Test]
    public function proxyCommandsUseLocalTempBetweenHosts(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'path' => '/srv/web', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        [$pull, $push] = (new FileSync())->proxyCommands($config, $config->files[0], '/tmp/ftmp');

        self::assertStringContainsString('deploy@o.example.com:/srv/app/fileadmin', $pull);
        self::assertStringContainsString('/tmp/ftmp', $pull);
        self::assertStringContainsString('/tmp/ftmp', $push);
        self::assertStringContainsString('deploy@t.example.com:/srv/web/fileadmin', $push);
    }
}
