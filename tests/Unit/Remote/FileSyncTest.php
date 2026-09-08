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
use KonradMichalik\SyncTool\Remote\FileSync;
use KonradMichalik\SyncTool\Remote\Transfer\TransferStrategyResolver;
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, Plans, RecordingCommandRunner};
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
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::receiver());

        self::assertTrue($recorder->ran('rsync'), 'transfers the entry via rsync');
        self::assertTrue($recorder->ran('--delete'), 'per-entry options are applied');
        self::assertTrue($recorder->ran('deploy@o.example.com:/srv/app/fileadmin'));
        self::assertTrue($recorder->ran('/var/www/fileadmin'));
    }

    /**
     * file-sync-tool configs commonly used a shell glob to sync a directory's
     * contents (`fileadmin/*`), relying on the *local* shell to expand it
     * before invoking rsync. RsyncCommandBuilder now quotes the whole path to
     * close a command-injection hole, so the shell never sees it and rsync
     * receives a literal, nonexistent `*` entry (rsync error, nothing
     * transferred). A trailing `/` already makes rsync sync the directory's
     * contents, so the literal asterisk must never reach rsync.
     */
    #[Test]
    public function syncNormalizesATrailingDirectoryGlobToATrailingSlash(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin/*', 'target' => 'fileadmin']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::receiver());

        self::assertTrue($recorder->ran('deploy@o.example.com:/srv/app/fileadmin/'));
        self::assertFalse($recorder->ran('fileadmin/*'), 'the literal asterisk must never reach rsync');
    }

    /**
     * Only the whole-directory case (`dir/*`) has a safe, semantically
     * equivalent rewrite (a trailing slash). A partial pattern like `*.jpg`
     * would need real glob expansion, which is out of scope here, so it is
     * left untouched rather than silently doing something else.
     */
    #[Test]
    public function syncLeavesAPartialGlobUntouched(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin/*.jpg', 'target' => 'fileadmin']],
        ]);

        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::receiver());

        self::assertTrue($recorder->ran('deploy@o.example.com:/srv/app/fileadmin/*.jpg'));
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
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::receiver());

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
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::proxy());

        self::assertMatchesRegularExpression(
            '#/php-sync-tool-[0-9a-f]{16}/fileadmin#',
            implode("\n", $recorder->commands),
            'pulls and pushes through a private local staging directory',
        );
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
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync($config, Plans::remoteCopy());

        self::assertTrue($recorder->ran('rsync'), 'runs a plain rsync on the remote host');
        self::assertTrue($recorder->ran('/srv/app/fileadmin'));
        self::assertTrue($recorder->ran('--archive'), 'global files_options are applied');
    }

    #[Test]
    public function syncLogsTransferringFilesAndTheActualCommandWhenLogGiven(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $logs = [];
        $recorder = new RecordingCommandRunner();
        (new FileSync(new TransferStrategyResolver(new FakeRunnerFactory($recorder))))->sync(
            $config,
            Plans::receiver(),
            static function (string $message) use (&$logs): void {
                $logs[] = $message;
            },
        );

        self::assertContains('Transferring files', $logs);
        self::assertNotEmpty(
            array_filter($logs, static fn (string $line): bool => str_contains($line, 'rsync')),
            'the actual rsync command is logged too, not just the generic status line',
        );
    }
}
