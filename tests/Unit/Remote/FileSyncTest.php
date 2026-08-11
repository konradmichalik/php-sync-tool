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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote;

use KonradMichalik\SyncTool\Config\{FileTransferConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\SyncMode;
use KonradMichalik\SyncTool\Remote\FileSync;
use KonradMichalik\SyncTool\Remote\Transfer\TransferStrategyResolver;
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FileSyncTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class FileSyncTest extends TestCase
{
    #[Test]
    public function resolvePathJoinsRelativeAndKeepsAbsolute(): void
    {
        self::assertSame('/srv/app/fileadmin', FileSync::resolvePath('fileadmin', '/srv/app'));
        self::assertSame('/srv/app/fileadmin', FileSync::resolvePath('fileadmin', '/srv/app/'));
        self::assertSame('/abs/path', FileSync::resolvePath('/abs/path', '/srv/app'));
        self::assertSame('/srv/app', FileSync::resolvePath('', '/srv/app'));
        self::assertSame('relative', FileSync::resolvePath('relative', ''));
    }

    #[Test]
    public function fromArrayReturnsDefaultsForEmptyInput(): void
    {
        $entry = FileTransferConfig::fromArray(null);

        self::assertSame('', $entry->origin);
        self::assertSame([], $entry->exclude);
    }

    #[Test]
    public function syncTransfersEachEntryDirectlyForReceiver(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin', 'options' => '--delete']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, SyncMode::Receiver);

        self::assertTrue($recorder->ran('rsync'), 'transfers the entry via rsync');
        self::assertTrue($recorder->ran('--delete'), 'per-entry options are applied');
        self::assertTrue($recorder->ran('deploy@o.example.com:/srv/app/fileadmin'));
        self::assertTrue($recorder->ran('/var/www/fileadmin'));
    }

    #[Test]
    public function syncAppliesGlobalFilesOptionsWhenEntryHasNone(): void
    {
        $config = SyncConfig::fromArray([
            'files_options' => '--archive',
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, SyncMode::Receiver);

        self::assertTrue($recorder->ran('--archive'));
    }

    #[Test]
    public function syncUsesLocalTempForProxyMode(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'path' => '/srv/web', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, SyncMode::Proxy);

        self::assertTrue($recorder->ran('php-sync-tool-fileadmin'), 'pulls and pushes through a local temp path');
        self::assertTrue($recorder->ran('rm -rf'), 'cleans up the local temp path');
    }

    #[Test]
    public function syncCopiesOnRemoteHostForSyncRemote(): void
    {
        $config = SyncConfig::fromArray([
            'files_options' => '--archive',
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'path' => '/srv/web', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, SyncMode::SyncRemote);

        self::assertTrue($recorder->ran('rsync'), 'runs a plain rsync on the remote host');
        self::assertTrue($recorder->ran('/srv/app/fileadmin'));
        self::assertTrue($recorder->ran('--archive'), 'global files_options are applied');
    }
}
