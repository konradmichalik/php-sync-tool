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

namespace KonradMichalik\SyncTool\Tests\Unit;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Output\Progress\NullSyncProgress;
use KonradMichalik\SyncTool\Remote\{CommandRunner, FileSync};
use KonradMichalik\SyncTool\Remote\Transfer\TransferStrategyResolver;
use KonradMichalik\SyncTool\Sync;
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, Plans, RecordingCommandRunner, RecordingSyncProgress};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * SyncTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncTest extends TestCase
{
    private const DEFAULT_RESPONSES = [
        'LIKE' => "col\ncache_pages\ncache_hash",
        'SHOW TABLES;' => "Tables_in_app\nusers\nposts",
        'echo VALID' => 'VALID',
        'stat ' => "d1 /tmp/_app_a.gz\nd2 /tmp/_app_b.gz\nd3 /tmp/_app_c.gz",
    ];
    /** @var list<string> */
    private array $logs = [];

    #[Test]
    public function syncLocalCreatesTransfersAndImports(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal());

        self::assertTrue($recorder->ran('mysqldump'), 'creates origin dump');
        self::assertTrue($recorder->ran('rsync'), 'transfers the dump via rsync, even for a local-to-local copy');
        self::assertTrue($recorder->ran('gunzip -c'), 'imports dump into target');
        self::assertContains('Transferring dump', $this->logs);
    }

    #[Test]
    public function receiverPullsDumpViaRsync(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $recorder = $this->runSync($config, Plans::receiver());

        self::assertTrue($recorder->ran('rsync'), 'transfers dump via rsync');
        self::assertContains('Transferring dump', $this->logs);
        self::assertNotEmpty(
            array_filter($this->logs, static fn (string $line): bool => str_contains($line, 'rsync')),
            'the actual rsync command is logged too, not just the generic status line',
        );
    }

    #[Test]
    public function useRsyncOptionsIsThreadedIntoTheDumpTransferCommand(): void
    {
        $config = SyncConfig::fromArray([
            'use_rsync_options' => '--bwlimit=1000',
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $recorder = $this->runSync($config, Plans::receiver());

        self::assertTrue($recorder->ran('--bwlimit=1000'), 'use_rsync_options flows into the dump transfer command');
    }

    #[Test]
    public function proxyModeUsesProxyTransfer(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $this->runSync($config, Plans::proxy());

        self::assertContains('Transferring dump via proxy (origin → local → target)', $this->logs);
    }

    #[Test]
    public function syncRemoteCopiesDumpOnRemoteHost(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $recorder = $this->runSync($config, Plans::remoteCopy());

        self::assertTrue($recorder->ran('rsync'), 'copies dump on remote host via rsync');
        self::assertContains('Transferring dump on the remote host', $this->logs);
    }

    #[Test]
    public function dumpModeSkipsTransferAndImport(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::dumpLocal());

        self::assertTrue($recorder->ran('mysqldump'));
        self::assertFalse($recorder->ran('cp '), 'no transfer in dump-only mode');
        self::assertFalse($recorder->ran('gunzip -c'), 'no import in dump-only mode');
    }

    #[Test]
    public function importModeSkipsCreateDumpAndUsesImportFile(): void
    {
        $config = SyncConfig::fromArray([
            'import' => '/backups/manual.sql.gz',
            'origin' => ['path' => '/var/www', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www2', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $recorder = $this->runSync($config, Plans::importLocal());

        self::assertFalse($recorder->ran('mysqldump'), 'import mode never dumps origin');
        self::assertTrue($recorder->ran('/backups/manual.sql.gz'), 'imports the provided file');
    }

    #[Test]
    public function keepDumpSkipsImport(): void
    {
        $config = $this->localConfig(['keep_dump' => true]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('mysqldump'));
        self::assertFalse($recorder->ran('gunzip -c'), 'keepDump leaves the target untouched');
    }

    #[Test]
    public function checkDumpFailureAborts(): void
    {
        $recorder = new RecordingCommandRunner(['echo VALID' => 'MISSING']);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('Dump validation failed');

        $this->syncWith($recorder)->run($this->localConfig(), Plans::syncLocal());
    }

    #[Test]
    public function clearDatabaseTruncateAfterDumpAndPostSqlAreExecuted(): void
    {
        $config = SyncConfig::fromArray([
            'clear_database' => true,
            'truncate_tables' => ['sessions', 'cache'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => [
                'path' => '/t',
                'after_dump' => '/seed/extra.sql.gz',
                'post_sql' => ['UPDATE config SET val = 1', ''],
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
            ],
        ]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertContains('Clearing target database', $this->logs);
        self::assertContains('Truncating tables', $this->logs);
        self::assertTrue($recorder->ran('/seed/extra.sql.gz'), 'imports the after-dump file');
        self::assertTrue($recorder->ran('UPDATE config SET val = 1'), 'runs post-import SQL');
    }

    #[Test]
    public function wildcardIgnoreTablesAreExpanded(): void
    {
        $config = $this->localConfig(['ignore_table' => ['cache_*', 'sys_log']]);

        $recorder = $this->runSync($config, Plans::dumpLocal());

        self::assertTrue($recorder->ran('cache_pages'), 'wildcard expands to matching tables');
        self::assertTrue($recorder->ran('cache_hash'));
        self::assertTrue($recorder->ran('sys_log'), 'plain ignore table kept verbatim');
    }

    #[Test]
    public function oldDumpsArePrunedWhenKeepDumpsSet(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'keep_dumps' => 1, 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = $this->runSync($config, Plans::dumpLocal());

        self::assertTrue($recorder->ran('rm -f'), 'removes dumps beyond the retention limit');
        self::assertContains('Removing old dump /tmp/_app_b.gz', $this->logs);
    }

    #[Test]
    public function filesOnlySyncsFilesAndSkipsDatabase(): void
    {
        $config = SyncConfig::fromArray([
            'files_only' => true,
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $recorder = $this->runSync($config, Plans::receiver());

        self::assertFalse($recorder->ran('mysqldump'), 'files-only skips the database');
        self::assertTrue($recorder->ran('rsync'), 'transfers files');
        self::assertContains('Synchronizing files', $this->logs);
        self::assertContains('Transferring files', $this->logs);
        self::assertNotEmpty(
            array_filter($this->logs, static fn (string $line): bool => str_contains($line, 'rsync')),
            'the actual file-transfer command is logged too, not just the generic status line',
        );
    }

    #[Test]
    public function withFilesSyncsBothDatabaseAndFiles(): void
    {
        $config = SyncConfig::fromArray([
            'with_files' => true,
            'origin' => ['path' => '/srv/app', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['path' => '/var/www', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('mysqldump'), 'syncs the database');
        self::assertTrue($recorder->ran('rsync'), 'syncs files too');
    }

    #[Test]
    public function resolvesEmptyDatabaseNameFromClientPath(): void
    {
        $config = SyncConfig::fromArray([
            'type' => 'symfony',
            'origin' => ['path' => '/app/.env'],
            'target' => ['path' => '/app2/.env'],
        ]);

        $recorder = new RecordingCommandRunner(
            self::DEFAULT_RESPONSES + ['cat ' => 'DATABASE_URL=mysql://u:p@h:3306/resolved_db'],
        );

        $this->syncWith($recorder)->run($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('resolved_db'), 'resolved db name flows into later commands');
    }

    #[Test]
    public function errorPhaseScriptsRunWhenSyncFails(): void
    {
        $config = SyncConfig::fromArray([
            'scripts' => ['error' => 'echo boom'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES, throwOn: 'mysqldump');
        $sync = $this->syncWith($recorder);

        try {
            $sync->run($config, Plans::syncLocal());
            self::fail('expected the sync to rethrow');
        } catch (SyncException) {
            self::assertTrue($recorder->ran('echo boom'), 'error-phase script executed');
        }
    }

    #[Test]
    public function namesEachPhaseAndCountsTheCompletedSteps(): void
    {
        $progress = new RecordingSyncProgress();

        $this->syncWith(new RecordingCommandRunner(self::DEFAULT_RESPONSES), $progress)
            ->run($this->localConfig(), Plans::syncLocal());

        self::assertContains('Creating origin dump', $progress->phases);
        self::assertContains('Importing dump', $progress->phases);
        self::assertNotEmpty(
            array_filter($progress->phases, static fn (string $phase): bool => str_starts_with($phase, 'Transferring ')),
            'the transfer phase is named after the payload',
        );
        self::assertSame(3, $progress->advances, 'dump, transfer and import each count once');
    }

    #[Test]
    public function countsNoStepForWorkThatFailed(): void
    {
        $progress = new RecordingSyncProgress();
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES, throwOn: 'mysqldump');

        try {
            $this->syncWith($recorder, $progress)->run($this->localConfig(), Plans::syncLocal());
            self::fail('Expected the failing dump to bubble up.');
        } catch (SyncException) {
            self::assertSame(['Creating origin dump'], $progress->phases);
            self::assertSame(0, $progress->advances);
        }
    }

    #[Test]
    public function countsOneStepPerSynchronizedFileEntry(): void
    {
        $progress = new RecordingSyncProgress();
        $config = $this->localConfig([
            'files_only' => true,
            'files' => [['origin' => '/o/fileadmin', 'target' => '/t/fileadmin'], ['origin' => '/o/uploads', 'target' => '/t/uploads']],
        ]);

        $this->syncWith(new RecordingCommandRunner(self::DEFAULT_RESPONSES), $progress)
            ->run($config, Plans::syncLocal());

        self::assertSame(['Transferring fileadmin', 'Transferring uploads'], $progress->phases);
        self::assertSame(2, $progress->advances);
    }

    #[Test]
    public function drivesAPostgresClientWithPostgresBinaries(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres']],
        ]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('pg_dump'), 'dumps with pg_dump');
        self::assertTrue($recorder->ran('psql'), 'imports with psql');
        self::assertFalse($recorder->ran('mysqldump'));
    }

    #[Test]
    public function refusesAPostgresSyncThatAsksForMysqlOnlyFeatures(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres']],
            'where' => 'id > 1',
        ]);
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES);

        try {
            $this->syncWith($recorder)->run($config, Plans::syncLocal());
            self::fail('Expected the unsupported where clause to abort the sync.');
        } catch (SyncException $exception) {
            self::assertStringContainsString('PostgreSQL does not support: where', $exception->getMessage());
            self::assertFalse($recorder->ran('pg_dump'), 'aborts before dumping anything');
        }
    }

    #[Test]
    public function masksConfiguredColumnsAfterTheImportAndBeforePostSql(): void
    {
        $config = $this->localConfig([
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
                'anonymize' => ['fe_users' => ['email' => 'email']],
                'post_sql' => ['UPDATE marker SET done = 1'],
            ],
        ]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        $importIndex = $this->indexOfCommand($recorder, 'gunzip -c');
        $maskIndex = $this->indexOfCommand($recorder, 'CONCAT(MD5(');
        $postSqlIndex = $this->indexOfCommand($recorder, 'UPDATE marker SET done = 1');

        self::assertGreaterThan($importIndex, $maskIndex, 'masking runs after the import');
        self::assertLessThan($postSqlIndex, $maskIndex, 'masking runs before post_sql');
    }

    #[Test]
    public function aFailedMaskingStatementAbortsTheSyncBeforePostSql(): void
    {
        $config = $this->localConfig([
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
                'anonymize' => ['fe_users' => ['email' => 'email']],
                'post_sql' => ['UPDATE marker SET done = 1'],
            ],
        ]);
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES, throwOn: 'CONCAT(MD5(');

        try {
            $this->syncWith($recorder)->run($config, Plans::syncLocal());
            self::fail('Expected the failing masking statement to abort the sync.');
        } catch (SyncException) {
            self::assertFalse($recorder->ran('UPDATE marker SET done = 1'), 'post_sql never runs');
        }
    }

    #[Test]
    public function runsNoMaskingWithoutRules(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal());

        self::assertFalse($recorder->ran('CONCAT(MD5('));
    }

    private function indexOfCommand(RecordingCommandRunner $recorder, string $needle): int
    {
        foreach ($recorder->commands as $index => $command) {
            if (str_contains($command, $needle)) {
                return $index;
            }
        }

        self::fail(sprintf('No command containing "%s" was run', $needle));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function localConfig(array $overrides = []): SyncConfig
    {
        return SyncConfig::fromArray($overrides + [
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);
    }

    private function runSync(SyncConfig $config, SyncPlan $plan): RecordingCommandRunner
    {
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES);
        $this->syncWith($recorder)->run($config, $plan);

        return $recorder;
    }

    private function syncWith(CommandRunner $recorder, ?RecordingSyncProgress $progress = null): Sync
    {
        $factory = new FakeRunnerFactory($recorder);
        $this->logs = [];

        return new Sync(
            runners: $factory,
            transferResolver: new TransferStrategyResolver($factory),
            fileSync: new FileSync(new TransferStrategyResolver($factory)),
            log: function (string $message): void {
                $this->logs[] = $message;
            },
            progress: $progress ?? new NullSyncProgress(),
        );
    }
}
