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

use KonradMichalik\SyncTool\Backup\DumpFileNamer;
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
        // Answers both dump-check probes: the file is there, and the archive is
        // intact. A test about either failing overrides its own command.
        'echo OK' => 'OK',
        'tail -n 5' => "--\n-- Dump completed on 2026-08-31 12:00:00",
        'stat ' => "d1 /tmp/_app_a.gz\nd2 /tmp/_app_b.gz\nd3 /tmp/_app_c.gz",
    ];

    /**
     * The trailer of a complete dump, in the dialect of the target being imported
     * into. A test whose target is Postgres has to say so, otherwise it asserts
     * against a dump mysqldump would have written.
     */
    private const POSTGRES_TRAILER = ['tail -n 5' => "--\n-- PostgreSQL database dump complete\n--"];
    /** @var list<string> */
    private array $logs = [];

    #[Test]
    public function syncLocalCreatesTransfersAndImports(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal());

        self::assertTrue($recorder->ran('mysqldump'), 'creates origin dump');
        self::assertTrue($recorder->ran('rsync'), 'transfers the dump via rsync, even for a local-to-local copy');
        self::assertTrue($recorder->ran('| mysql'), 'imports dump into target');
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
        self::assertFalse($recorder->ran('| mysql'), 'no import in dump-only mode');
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

        self::assertFalse($recorder->ran('mysqldump --defaults-extra-file'), 'import mode never dumps origin');
        self::assertTrue($recorder->ran('/backups/manual.sql.gz'), 'imports the provided file');
    }

    #[Test]
    public function keepDumpSkipsImport(): void
    {
        $config = $this->localConfig(['keep_dump' => true]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('mysqldump'));
        self::assertFalse($recorder->ran('| mysql'), 'keepDump leaves the target untouched');
    }

    #[Test]
    public function checkDumpFailureAborts(): void
    {
        $recorder = new RecordingCommandRunner([]);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('is missing or empty');

        $this->syncWith($recorder)->run($this->localConfig(), Plans::syncLocal());
    }

    /**
     * The case a size check cannot see: a dump the tool started but never
     * finished still has content, and importing it silently loses rows.
     */
    #[Test]
    public function truncatedDumpAborts(): void
    {
        $recorder = new RecordingCommandRunner(
            ['tail -n 5' => "INSERT INTO `users` VALUES (1,'a'),(2,'b"] + self::DEFAULT_RESPONSES,
        );

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('is incomplete');

        $this->syncWith($recorder)->run($this->localConfig(), Plans::syncLocal());
    }

    /**
     * gzip's checksum is the only thing that separates a stream that decompressed
     * correctly from one that merely decompressed. Reading the trailer cannot see
     * it: `gunzip -c | tail` reports tail's status, so a damaged archive still
     * produced its last lines, marker included.
     */
    #[Test]
    public function aDamagedGzipArchiveAbortsEvenThoughItsTrailerLooksRight(): void
    {
        $recorder = new RecordingCommandRunner(['gunzip -t' => ''] + self::DEFAULT_RESPONSES);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('is not a valid gzip archive');

        $this->syncWith($recorder)->run($this->localConfig(), Plans::syncLocal());
    }

    /**
     * A row carrying the marker text would satisfy a substring match over the
     * whole trailer and validate a dump that stops mid-statement.
     */
    #[Test]
    public function markerTextInsideARowDoesNotValidateATruncatedDump(): void
    {
        $recorder = new RecordingCommandRunner(
            ['tail -n 5' => "INSERT INTO `pages` VALUES (7,'log: -- Dump completed on 2020-01-01'),(8,'par"]
            + self::DEFAULT_RESPONSES,
        );

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('is incomplete');

        $this->syncWith($recorder)->run($this->localConfig(), Plans::syncLocal());
    }

    /**
     * The completion line carries a timestamp after the marker, so it is matched
     * by how it opens rather than by equality.
     */
    #[Test]
    public function theCompletionLineIsRecognisedWithItsTimestamp(): void
    {
        $recorder = $this->runSync(
            $this->localConfig(),
            Plans::syncLocal(),
            ['tail -n 5' => "UNLOCK TABLES;\n\n-- Dump completed on 2026-09-01  9:15:02"],
        );

        self::assertTrue($recorder->ran('| mysql'), 'the import goes ahead');
    }

    /**
     * A MariaDB 11 endpoint has no `mysqldump`, so a configuration that never
     * mentioned `type` used to name a binary that is not there.
     */
    #[Test]
    public function usesMariadbBinariesWhenTheEndpointHasNoMysqlOnes(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal(), ['command -v' => 'other']);

        self::assertTrue($recorder->ran('mariadb-dump --defaults-extra-file'), 'dumps with mariadb-dump');
        self::assertFalse($recorder->ran('mysqldump --defaults-extra-file'));
    }

    /**
     * Counted in the dump rather than asked of the database, so it reflects what
     * the export filters actually let through.
     */
    #[Test]
    public function reportsHowManyTablesTheDumpCarries(): void
    {
        $this->runSync($this->localConfig(), Plans::syncLocal(), ['grep -c "CREATE TABLE"' => '17']);

        self::assertContains('17 table(s) exported', $this->logs);
    }

    #[Test]
    public function saysNothingAboutTablesWhenTheCountCannotBeRead(): void
    {
        $this->runSync($this->localConfig(), Plans::syncLocal(), ['grep -c "CREATE TABLE"' => '']);

        self::assertEmpty(
            array_filter($this->logs, static fn (string $line): bool => str_contains($line, 'table(s) exported')),
        );
    }

    #[Test]
    public function reportsTheDatabaseVersionItFound(): void
    {
        $this->runSync($this->localConfig(), Plans::syncLocal(), ['SELECT VERSION()' => "VERSION()\n11.4.2-MariaDB"]);

        self::assertContains('Database version: MariaDB 11.4.2', $this->logs);
    }

    #[Test]
    public function completeDumpPassesTheCheck(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal());

        self::assertTrue($recorder->ran('| tail -n 5'), 'the dump trailer is read');
        self::assertTrue($recorder->ran('| mysql'), 'and the import follows');
    }

    /**
     * An external `--import-file` may be a plain .sql, which has to be read
     * without gunzip.
     */
    #[Test]
    public function plainImportFileIsReadWithoutGunzip(): void
    {
        $config = $this->localConfig(['import' => '/dumps/plain.sql']);

        $recorder = $this->runSync($config, Plans::importLocal());

        self::assertTrue($recorder->ran("cat '/dumps/plain.sql' | tail -n 5"), 'the dump trailer is read without gunzip');
        self::assertTrue($recorder->ran('< /dumps/plain.sql'), 'the import reads the file directly');
        self::assertFalse($recorder->ran('gunzip'), 'gunzip is never invoked for a plain file');
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

    /**
     * The safety copy is only worth anything if it is taken while the target is
     * still intact, so before clearing, truncating and importing.
     */
    #[Test]
    public function theTargetIsDumpedBeforeItIsOverwritten(): void
    {
        $config = $this->localConfig(['backup_before_import' => true, 'clear_database' => true]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        $backup = $this->indexOfCommand($recorder, 'sync-tool_backup_');

        self::assertLessThan($this->indexOfCommand($recorder, '| mysql'), $backup, 'the backup runs before the import');
        self::assertLessThan($this->indexOfCommand($recorder, 'DROP TABLE'), $backup, 'and before the target is cleared');
    }

    #[Test]
    public function noBackupIsTakenUnlessItIsAskedFor(): void
    {
        $recorder = $this->runSync($this->localConfig(), Plans::syncLocal());

        self::assertFalse($recorder->ran('sync-tool_backup_'));
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

    /**
     * One query for the whole list rather than one per pattern, each of which was
     * a full round trip to the endpoint before the first table was touched.
     */
    #[Test]
    public function everyWildcardIsResolvedByOneQuery(): void
    {
        $config = $this->localConfig(['ignore_table' => ['cache_*', 'cf_*', 'sys_log*', 'be_sessions']]);

        $recorder = $this->runSync($config, Plans::dumpLocal());

        self::assertCount(
            1,
            array_filter($recorder->commands, static fn (string $command): bool => str_contains($command, 'LIKE')),
        );
    }

    #[Test]
    public function wildcardTruncateTablesAreExpandedAgainstTheTarget(): void
    {
        $config = SyncConfig::fromArray([
            'truncate_tables' => ['cache_*', 'sessions'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = $this->runSync($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('TRUNCATE TABLE `cache_pages`'), 'the wildcard expands to matching tables');
        self::assertTrue($recorder->ran('TRUNCATE TABLE `cache_hash`'));
        self::assertTrue($recorder->ran('TRUNCATE TABLE `sessions`'), 'a plain name is kept verbatim');
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

    /**
     * The default dump directory is /tmp/, so a retention glob of `*` would put
     * every foreign .sql and .gz on the deletion list.
     */
    #[Test]
    public function retentionOnlyListsDumpsWrittenByThisTool(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'keep_dumps' => 1, 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = $this->runSync($config, Plans::dumpLocal());

        self::assertTrue(
            $recorder->ran('/tmp/'.DumpFileNamer::PREFIX.'*'),
            'the listing is scoped to our own dump prefix',
        );
        self::assertFalse($recorder->ran('stat -c "%y %n" /tmp/* '), 'never globs the whole dump directory');
    }

    /**
     * The dialect belongs to the host the command runs on, not to the one running
     * the tool. A remote macOS endpoint used to be handed GNU syntax, the listing
     * failed, and retention quietly stopped removing anything.
     */
    #[Test]
    public function retentionUsesTheStatDialectOfTheHostItRunsOn(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'keep_dumps' => 1, 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = $this->runSync($config, Plans::dumpLocal(), ['uname -s' => 'Darwin']);

        self::assertTrue($recorder->ran('stat -f "%m %N"'), 'BSD stat for a Darwin host');
        self::assertFalse($recorder->ran('stat -c'));
    }

    #[Test]
    public function retentionUsesGnuStatOnLinux(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'keep_dumps' => 1, 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        $recorder = $this->runSync($config, Plans::dumpLocal(), ['uname -s' => 'Linux']);

        self::assertTrue($recorder->ran('stat -c "%Y %n"'));
        self::assertFalse($recorder->ran('stat -f'));
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
        self::assertContains('Reading database credentials from /app/.env', $this->logs);
        self::assertContains('Reading database credentials from /app2/.env', $this->logs);
    }

    #[Test]
    public function explicitDatabaseHostOverridesTheOneDetectedFromClientPath(): void
    {
        $config = SyncConfig::fromArray([
            'type' => 'symfony',
            // The app reaches its database under a Docker service name that only
            // resolves inside the container network. The dump runs on the host, so
            // the host has to be overridden while the rest stays auto-detected.
            'origin' => ['path' => '/app/.env', 'db' => ['host' => '127.0.0.1']],
            'target' => ['db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres', 'host' => '10.9.9.9']],
        ]);

        $recorder = new RecordingCommandRunner(
            self::POSTGRES_TRAILER + self::DEFAULT_RESPONSES + ['cat ' => 'DATABASE_URL=postgresql://u:p@postgres:5432/resolved_db'],
        );

        $this->syncWith($recorder)->run($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('resolved_db'), 'name still comes from the detected DATABASE_URL');
        self::assertTrue($recorder->ran('-h 127.0.0.1'), 'the explicitly configured host wins');
        self::assertFalse($recorder->ran('-h postgres'), 'the detected host is discarded');
    }

    #[Test]
    public function anExplicitValueSatisfiesACredentialTheDetectedConfigDoesNotCarry(): void
    {
        $config = SyncConfig::fromArray([
            'type' => 'symfony',
            'origin' => ['path' => '/app/.env', 'db' => ['password' => 'from-config']],
            'target' => ['db' => ['name' => 'app', 'user' => 'u', 'password' => 'p', 'type' => 'postgres', 'host' => '10.9.9.9']],
        ]);

        // Empty password in the application's own configuration. The credential
        // check has to see the configured one, otherwise the override is pointless.
        $recorder = new RecordingCommandRunner(
            self::POSTGRES_TRAILER + self::DEFAULT_RESPONSES + ['cat ' => 'DATABASE_URL=postgresql://u:@postgres:5432/resolved_db'],
        );

        $this->syncWith($recorder)->run($config, Plans::syncLocal());

        self::assertTrue($recorder->ran('resolved_db'), 'the sync runs instead of failing the credential check');
    }

    #[Test]
    public function errorPhaseScriptsRunWhenSyncFails(): void
    {
        $config = SyncConfig::fromArray([
            'scripts' => ['error' => 'echo boom'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);

        // The dump itself, not the `command -v mysqldump` probe that precedes it.
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES, throwOn: 'mysqldump --defaults-extra-file');
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
        // The dump itself, not the `command -v mysqldump` probe that precedes it.
        $recorder = new RecordingCommandRunner(self::DEFAULT_RESPONSES, throwOn: 'mysqldump --defaults-extra-file');

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

        $recorder = $this->runSync($config, Plans::syncLocal(), self::POSTGRES_TRAILER);

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

        $importIndex = $this->indexOfCommand($recorder, '| mysql');
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

    /**
     * @param array<string, string> $responses substring => canned stdout, taking
     *                                         precedence over the defaults
     */
    private function runSync(SyncConfig $config, SyncPlan $plan, array $responses = []): RecordingCommandRunner
    {
        $recorder = new RecordingCommandRunner($responses + self::DEFAULT_RESPONSES);
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
