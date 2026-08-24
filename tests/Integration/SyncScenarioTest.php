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

namespace KonradMichalik\SyncTool\Tests\Integration;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function sprintf;
use function trim;

/**
 * SyncScenarioTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncScenarioTest extends TestCase
{
    private const COMPOSE_DIR = __DIR__.'/../../docker';

    protected function setUp(): void
    {
        if (!$this->isStackRunning()) {
            self::markTestSkipped('Docker stack is not running. Start it with: cd docker && docker compose up -d --build');
        }
    }

    #[Test]
    public function receiverSyncCopiesAllRowsFromOriginToTarget(): void
    {
        $this->resetDatabases();

        self::assertSame(3, $this->rowCount('db1'), 'origin should start with 3 rows');
        self::assertSame(0, $this->rowCount('db2'), 'target should start empty');

        $result = $this->compose([
            'exec', '-T', 'www2',
            'php', '/app/bin/sync-tool', '-f', '/app/docker/configs/receiver.yaml', '-y',
        ]);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'target should hold the origin rows after sync');
    }

    #[Test]
    public function senderSyncPushesAllRowsToRemoteTarget(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www1', 'sender.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function proxySyncTransfersBetweenTwoRemoteHosts(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('proxy', 'proxy.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function receiverSyncViaSftpFallbackCopiesAllRows(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--no-rsync']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function clearDatabaseDropsOrphanTablesBeforeImport(): void
    {
        $this->resetDatabases();
        $this->mysql('db2', 'CREATE TABLE orphan (id INT);');
        self::assertSame(1, $this->tableCount('db2', 'orphan'), 'orphan table should exist before sync');

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--clear-database']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
        self::assertSame(0, $this->tableCount('db2', 'orphan'), 'orphan table should be dropped by --clear-database');
    }

    #[Test]
    public function autoDetectsWordpressCredentialsFromConfigFile(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'autodetect.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'credentials extracted from wp-config.php should drive a working sync');
    }

    #[Test]
    public function postSqlRunsAfterImport(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'postsql.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(4, $this->rowCount('db2'), 'post_sql INSERT adds one row on top of the 3 imported');
    }

    #[Test]
    public function whereClauseLimitsExportedRows(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--where=id <= 2']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(2, $this->rowCount('db2'), 'only rows matching the WHERE clause are transferred');
    }

    #[Test]
    public function keepDumpSkipsTheImport(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--keep-dump']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(0, $this->rowCount('db2'), 'keep-dump creates the dump but skips the import');
    }

    #[Test]
    public function withFilesSyncsDatabaseAndTransfersFile(): void
    {
        $this->resetDatabases();
        $this->compose(['exec', '-T', 'www2', 'rm', '-f', '/tmp/synced-file.txt']);

        $result = $this->runSyncTool('www2', 'withfiles.yaml', ['--with-files']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'database still syncs alongside files');
        $transferred = $this->compose(['exec', '-T', 'www2', 'cat', '/tmp/synced-file.txt'])->getOutput();
        self::assertStringContainsString('php-sync-tool-file-transfer-ok', $transferred);
    }

    #[Test]
    public function sftpFallbackSyncsADirectoryRecursivelyWhileRespectingExcludes(): void
    {
        $this->resetDatabases();
        $this->compose(['exec', '-T', 'www2', 'rm', '-rf', '/tmp/synced-dir']);

        $result = $this->runSyncTool('www2', 'sftp-files.yaml', ['--no-rsync', '--with-files']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'database still syncs alongside files');

        $kept = $this->compose(['exec', '-T', 'www2', 'cat', '/tmp/synced-dir/keep.txt'])->getOutput();
        self::assertStringContainsString('php-sync-tool-sftp-dir-keep', $kept);

        $nested = $this->compose(['exec', '-T', 'www2', 'cat', '/tmp/synced-dir/nested/keep-nested.txt'])->getOutput();
        self::assertStringContainsString('php-sync-tool-sftp-dir-keep-nested', $nested);

        $excluded = $this->compose(['exec', '-T', 'www2', 'test', '-f', '/tmp/synced-dir/skip.log']);
        self::assertFalse($excluded->isSuccessful(), 'the excluded *.log file must not have been transferred');
    }

    #[Test]
    public function importFileLoadsAnExistingDumpIntoTheTarget(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'importfile.yaml', ['--import-file=/app/docker/fixtures/seed.sql']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(2, $this->rowCount('db2'), 'the two rows from the seed dump are imported');
    }

    #[Test]
    #[DataProvider('frameworkConfigs')]
    public function autoDetectsCredentialsForFramework(string $config): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', $config);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function frameworkConfigs(): iterable
    {
        yield 'symfony (DATABASE_URL)' => ['framework-symfony.yaml'];
        yield 'typo3 (LocalConfiguration.php)' => ['framework-typo3.yaml'];
        yield 'drupal (settings.php)' => ['framework-drupal.yaml'];
        yield 'laravel (.env)' => ['framework-laravel.yaml'];
    }

    #[Test]
    public function truncateTablesEmptiesConfiguredTablesBeforeImport(): void
    {
        $this->resetDatabases();
        $this->mysql('db2', 'DROP TABLE IF EXISTS legacy; CREATE TABLE legacy (id INT); INSERT INTO legacy VALUES (1),(2);');
        // Two tables the wildcard has to find, and one it must not touch.
        $this->mysql('db2', 'DROP TABLE IF EXISTS cache_a, cache_b, keep_me;');
        $this->mysql('db2', 'CREATE TABLE cache_a (id INT); INSERT INTO cache_a VALUES (1),(2);');
        $this->mysql('db2', 'CREATE TABLE cache_b (id INT); INSERT INTO cache_b VALUES (1);');
        $this->mysql('db2', 'CREATE TABLE keep_me (id INT); INSERT INTO keep_me VALUES (1),(2),(3);');
        self::assertSame(2, $this->rowCountOf('db2', 'legacy'), 'legacy should hold rows before the sync');

        $result = $this->runSyncTool('www2', 'truncate.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCountOf('db2', 'person'), 'person is imported from the origin');
        self::assertSame(0, $this->rowCountOf('db2', 'legacy'), 'legacy is truncated before the import');
        self::assertSame(0, $this->rowCountOf('db2', 'cache_a'), 'the wildcard expands against the target');
        self::assertSame(0, $this->rowCountOf('db2', 'cache_b'));
        self::assertSame(3, $this->rowCountOf('db2', 'keep_me'), 'a table outside the pattern is left alone');
    }

    #[Test]
    public function jumpHostSyncReachesIsolatedOriginViaBastion(): void
    {
        $seed = 'DROP TABLE IF EXISTS person; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255)); INSERT INTO person (name) VALUES ("Alice"),("Bob"),("Carol");';
        $this->mysql('db3', $seed);
        $this->mysql('db2', 'DROP TABLE IF EXISTS person; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255));');

        $result = $this->runSyncTool('www2', 'jumphost.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'origin reached only via the bastion still syncs');
    }

    #[Test]
    public function ignoreTablesWildcardExcludesMatchingTables(): void
    {
        $this->resetDatabases();
        $this->mysql('db1', 'CREATE TABLE cache_a (id INT); CREATE TABLE cache_b (id INT); INSERT INTO cache_a VALUES (1);');

        $result = $this->runSyncTool('www2', 'ignore-wildcard.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCountOf('db2', 'person'), 'person is synced');
        self::assertSame(0, $this->tableCount('db2', 'cache_a'), 'cache_* tables are excluded from the dump');
        self::assertSame(0, $this->tableCount('db2', 'cache_b'), 'cache_* tables are excluded from the dump');
    }

    #[Test]
    public function postgresReceiverSyncCopiesAllRowsFromOriginToTarget(): void
    {
        $this->compose(['exec', '-T', 'pgdb2', 'psql', '-U', 'db', '-d', 'db', '-c', 'TRUNCATE TABLE person;']);
        self::assertSame(0, $this->postgresRowCount('pgdb2', 'person'), 'target starts empty');

        $result = $this->runSyncTool('www2', 'postgres.yaml');

        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());
        self::assertSame(3, $this->postgresRowCount('pgdb2', 'person'));
    }

    /**
     * The safety copy has to be a real, non-empty dump of the target, written
     * before the import replaces the data it holds.
     */
    #[Test]
    public function theTargetIsBackedUpBeforeTheImport(): void
    {
        $this->resetDatabases();
        $this->compose(['exec', '-T', 'www2', 'sh', '-c', 'rm -f /tmp/sync-tool_backup_*']);
        $this->mysql('db2', 'DROP TABLE IF EXISTS gone_after_import; CREATE TABLE gone_after_import (id INT); INSERT INTO gone_after_import VALUES (1);');

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--backup-before-import', '--clear-database']);

        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());
        self::assertSame(3, $this->rowCount('db2'), 'the sync itself still runs');

        $backup = $this->compose(['exec', '-T', 'www2', 'sh', '-c', 'ls -S /tmp/sync-tool_backup_*.gz | head -1']);
        $file = trim($backup->getOutput());
        self::assertNotSame('', $file, 'a backup file is left behind on the target');

        $contents = $this->compose(['exec', '-T', 'www2', 'sh', '-c', 'gunzip -c '.$file]);
        self::assertStringContainsString('gone_after_import', $contents->getOutput(), 'it holds the target as it was before the import');
    }

    /**
     * Auto-detection decides the database system, not just the credentials: a
     * framework file naming a PostgreSQL driver has to reach for `pg_dump`, on
     * the PostgreSQL default port, without a `db` block on that endpoint.
     */
    #[Test]
    public function autoDetectionReadsThePostgresDriverFromTheFrameworkConfig(): void
    {
        $this->compose(['exec', '-T', 'pgdb2', 'psql', '-U', 'db', '-d', 'db', '-c', 'TRUNCATE TABLE person;']);
        self::assertSame(0, $this->postgresRowCount('pgdb2', 'person'), 'target starts empty');

        $result = $this->runSyncTool('www2', 'framework-typo3-postgres.yaml', ['-vv']);

        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());
        $log = $result->getOutput().$result->getErrorOutput();
        self::assertStringContainsString('pg_dump ', $log);
        self::assertStringNotContainsString('mysqldump ', $log);
        self::assertSame(3, $this->postgresRowCount('pgdb2', 'person'));
    }

    /**
     * MariaDB 11 deprecated the `mysqldump` and `mysql` symlinks. `db.type:
     * mariadb` has to reach for `mariadb-dump` and `mariadb` instead, and the
     * result has to be the same sync.
     */
    #[Test]
    public function aMariadbEndpointSyncsThroughTheMariadbBinaries(): void
    {
        $result = $this->runSyncTool('www2', 'mariadb.yaml', ['-vv']);

        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());

        $log = $result->getOutput().$result->getErrorOutput();
        self::assertStringContainsString('mariadb-dump ', $log);
        self::assertStringNotContainsString('mysqldump ', $log);
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function anonymizationReplacesEveryConfiguredColumnAfterTheImport(): void
    {
        $result = $this->runSyncTool('www2', 'anonymize.yaml');
        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());

        $row = $this->mysqlRow('db2', "SELECT email, password, display_name, notes FROM account WHERE email LIKE '%@example.invalid' LIMIT 1");

        self::assertMatchesRegularExpression('#^[0-9a-f]{32}@example\\.invalid$#', $row[0], 'email is hashed into the reserved domain');
        self::assertMatchesRegularExpression('#^[0-9a-f]{32}$#', $row[1], 'password is hashed');
        self::assertSame('Redacted', $row[2], 'display name is the configured static value');
        self::assertSame('NULL', $row[3], 'notes are nulled');

        $plaintext = $this->compose(['exec', '-T', 'db2', 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', "SELECT COUNT(*) FROM account WHERE email = 'alice@example.com'"])->getOutput();
        self::assertSame(0, (int) trim($plaintext), 'no plaintext address survives');
    }

    /**
     * Auto-detected credentials rebuild the endpoint objects mid-run. That used to
     * drop the target's anonymize rules, so masking was skipped without a warning
     * in exactly the configuration style the documentation recommends. Every unit
     * test passed, because no scenario crossed auto-detection with a target-side
     * feature.
     */
    #[Test]
    public function anonymizationSurvivesCredentialAutoDetectionOnBothEndpoints(): void
    {
        $result = $this->runSyncTool('www2', 'autodetect-anonymize.yaml');
        self::assertTrue($result->isSuccessful(), $result->getOutput().$result->getErrorOutput());

        self::assertStringContainsString(
            'Anonymizing data',
            $result->getOutput().$result->getErrorOutput(),
            'the masking step has to run, not just be counted',
        );

        $plaintext = $this->compose([
            'exec', '-T', 'db2', 'mariadb', '-udb', '-pdb', 'db', '-N', '-e',
            "SELECT COUNT(*) FROM account WHERE email IN ('alice@example.com', 'bob@example.com')",
        ])->getOutput();
        self::assertSame(0, (int) trim($plaintext), 'no plaintext address reaches the target');

        $row = $this->mysqlRow('db2', "SELECT email, display_name FROM account WHERE email LIKE '%@example.invalid' LIMIT 1");
        self::assertMatchesRegularExpression('#^[0-9a-f]{32}@example\\.invalid$#', $row[0]);
        self::assertSame('Redacted', $row[1]);
    }

    private function resetDatabases(): void
    {
        $this->mysql('db1', 'DROP TABLE IF EXISTS person, cache_a, cache_b; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255)); INSERT INTO person (name) VALUES ("Alice"),("Bob"),("Carol");');
        // cache_* and keep_me are left behind by the truncate scenario, and the
        // ignore-wildcard scenario asserts they are absent from the target.
        $this->mysql('db2', 'DROP TABLE IF EXISTS legacy, cache_a, cache_b, keep_me; DROP TABLE IF EXISTS person; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255));');
    }

    private function rowCount(string $dbService): int
    {
        return $this->rowCountOf($dbService, 'person');
    }

    private function rowCountOf(string $dbService, string $table): int
    {
        $output = $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', sprintf('SELECT COUNT(*) FROM %s;', $table)])->getOutput();

        return (int) trim($output);
    }

    private function mysql(string $dbService, string $sql): void
    {
        $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-e', $sql]);
    }

    /**
     * @return list<string>
     */
    private function mysqlRow(string $dbService, string $sql): array
    {
        $output = $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', $sql])->getOutput();

        return array_map(trim(...), explode("\t", trim($output)));
    }

    private function postgresRowCount(string $dbService, string $table): int
    {
        $output = $this->compose([
            'exec', '-T', $dbService,
            'psql', '-U', 'db', '-d', 'db', '-t', '-A', '-c', sprintf('SELECT COUNT(*) FROM %s;', $table),
        ])->getOutput();

        return (int) trim($output);
    }

    private function tableCount(string $dbService, string $table): int
    {
        $output = $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', sprintf("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db' AND table_name='%s';", $table)])->getOutput();

        return (int) trim($output);
    }

    /**
     * @param list<string> $extraArgs
     */
    private function runSyncTool(string $node, string $configFile, array $extraArgs = []): Process
    {
        return $this->compose([
            'exec', '-T', $node,
            'php', '/app/bin/sync-tool', '-f', '/app/docker/configs/'.$configFile, '-y',
            ...$extraArgs,
        ]);
    }

    private function isStackRunning(): bool
    {
        $process = $this->compose(['ps', '--status', 'running', '--services']);

        return $process->isSuccessful() && str_contains($process->getOutput(), 'www2');
    }

    /**
     * @param list<string> $arguments
     */
    private function compose(array $arguments): Process
    {
        $process = new Process(['docker', 'compose', ...$arguments], self::COMPOSE_DIR);
        $process->setTimeout(120.0);
        $process->run();

        return $process;
    }
}
