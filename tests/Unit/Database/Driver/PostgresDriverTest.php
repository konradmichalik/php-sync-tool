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

namespace KonradMichalik\SyncTool\Tests\Unit\Database\Driver;

use KonradMichalik\SyncTool\Config\{AnonymizationRule, DatabaseConfig, SyncConfig};
use KonradMichalik\SyncTool\Database\Driver\PostgresDriver;
use KonradMichalik\SyncTool\Database\DumpRequest;
use KonradMichalik\SyncTool\Enum\{AnonymizationStrategy, DatabaseSystem};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PostgresDriverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class PostgresDriverTest extends TestCase
{
    private PostgresDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new PostgresDriver();
    }

    #[Test]
    public function reportsItsSystem(): void
    {
        self::assertSame(DatabaseSystem::PostgreSQL, $this->driver->system());
    }

    #[Test]
    public function dumpsWithPgDumpThroughGzipAndPassesThePasswordByFile(): void
    {
        $command = $this->driver->dumpCommand(new DumpRequest($this->db(), '/tmp/.pgpass_ab12', '/tmp/app.sql'));

        self::assertSame(
            'sync_status=$( { { PGPASSFILE=/tmp/.pgpass_ab12 pg_dump --no-owner --no-privileges --clean --if-exists'
            .' -h db -p 5432 -U u -d app; echo $? >&3; } | gzip > /tmp/app.sql.gz; } 3>&1 ) '
            .'&& [ "$sync_status" -eq 0 ]',
            $command,
        );
    }

    #[Test]
    public function rendersExportAndIgnoreTablesAsPgDumpTableFilters(): void
    {
        $command = $this->driver->dumpCommand(new DumpRequest(
            $this->db(),
            '/tmp/.pgpass_ab12',
            '/tmp/app.sql',
            exportTables: ['users'],
            ignoreTables: ['cache_pages'],
        ));

        self::assertStringContainsString('-t users', $command);
        self::assertStringContainsString('-T cache_pages', $command);
    }

    #[Test]
    public function importsAGzippedDumpThroughPsqlAndStopsOnTheFirstError(): void
    {
        self::assertSame(
            'sync_status=$( { { gunzip -c /tmp/app.sql.gz; echo $? >&3; } | PGPASSFILE=/tmp/.pgpass_ab12 psql '
            .'-v ON_ERROR_STOP=1 -q -h db -p 5432 -U u -d app > /dev/null; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $this->driver->importCommand($this->db(), '/tmp/.pgpass_ab12', '/tmp/app.sql.gz'),
        );
    }

    #[Test]
    public function importsAPlainDumpByRedirection(): void
    {
        self::assertStringEndsWith(
            ' -d app < /tmp/app.sql',
            $this->driver->importCommand($this->db(), '/tmp/.pgpass_ab12', '/tmp/app.sql'),
        );
    }

    #[Test]
    public function executesSqlUnquotedAndWithoutColumnDecoration(): void
    {
        self::assertSame(
            "PGPASSFILE=/tmp/.pgpass_ab12 psql -v ON_ERROR_STOP=1 -t -A -h db -p 5432 -U u -d app -c 'SELECT 1'",
            $this->driver->execCommand($this->db(), '/tmp/.pgpass_ab12', 'SELECT 1'),
        );
    }

    #[Test]
    public function escapesSingleQuotesInTheExecutedSql(): void
    {
        $command = $this->driver->execCommand($this->db(), '/tmp/.pgpass_ab12', "UPDATE t SET a = 'b'");

        // Shell::quote() escapes an inner single quote as '"'"'.
        self::assertStringContainsString('-c \'UPDATE t SET a = \'"\'"\'b\'"\'"\'\'', $command);
    }

    #[Test]
    public function listsTablesFromThePublicSchemaAndReadsThePlainOutput(): void
    {
        self::assertSame(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public';",
            $this->driver->listTablesSql(),
        );
        self::assertSame(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' "
            ."AND (tablename LIKE 'cache_%') ORDER BY tablename;",
            $this->driver->listTablesMatchingSql('app', ['cache_%']),
        );
        self::assertSame(['users', 'posts'], $this->driver->parseTableList("users\nposts\n\n"));
    }

    #[Test]
    public function dropsAndTruncatesInOneCascadingStatement(): void
    {
        self::assertSame(
            'DROP TABLE IF EXISTS users, posts CASCADE;',
            $this->driver->dropTablesStatement(['users', 'posts']),
        );
        self::assertSame(
            'TRUNCATE TABLE sys_log RESTART IDENTITY CASCADE;',
            $this->driver->truncateTablesStatement(['sys_log']),
        );
        self::assertNull($this->driver->dropTablesStatement([]));
        self::assertNull($this->driver->truncateTablesStatement([]));
    }

    #[Test]
    public function namesTheMysqlOnlyFeaturesItCannotExpress(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'type' => 'postgres']],
            'where' => 'id > 1',
            'additional_mysqldump_options' => '--skip-lock-tables',
        ]);

        self::assertSame(['where', 'additional_mysqldump_options'], $this->driver->unsupportedFeatures($config, $config->origin->db));
    }

    #[Test]
    public function reportsNothingUnsupportedForAPlainSync(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'type' => 'postgres']],
        ]);

        self::assertSame([], $this->driver->unsupportedFeatures($config, $config->origin->db));
    }

    /**
     * The ssl_* keys configure a MySQL client. Silently ignoring them could leave
     * a connection unencrypted that the configuration says is protected.
     */
    #[Test]
    public function refusesTlsSettingsItCannotHonour(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'postgres', 'ssl_skip_verify' => true]],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'type' => 'postgres']],
        ]);

        self::assertSame(['db.ssl_*'], $this->driver->unsupportedFeatures($config, $config->origin->db));
        self::assertSame([], $this->driver->unsupportedFeatures($config, $config->target->db), 'only the endpoint that configured them');
    }

    #[Test]
    public function writesAPgpassLineAndARandomPath(): void
    {
        self::assertSame("db:5432:app:u:p\n", $this->driver->credentialsContent($this->db()));
        self::assertMatchesRegularExpression('#^/tmp/\.pgpass_[0-9a-f]{16}$#', $this->driver->credentialsPath());
    }

    #[Test]
    public function buildsAnUpdateStatementPerAnonymizationRule(): void
    {
        $statements = $this->driver->anonymizeStatements([
            new AnonymizationRule('fe_users', 'email', AnonymizationStrategy::Email),
            new AnonymizationRule('fe_users', 'password', AnonymizationStrategy::Hash),
            new AnonymizationRule('fe_users', 'name', AnonymizationStrategy::StaticValue, 'Redacted'),
            new AnonymizationRule('sys_log', 'details', AnonymizationStrategy::Nullify),
        ]);

        self::assertSame([
            "UPDATE fe_users SET email = md5(email) || '@example.invalid';",
            'UPDATE fe_users SET password = md5(password);',
            "UPDATE fe_users SET name = 'Redacted';",
            'UPDATE sys_log SET details = NULL;',
        ], $statements);
    }

    #[Test]
    public function escapesASingleQuoteInAStaticValue(): void
    {
        self::assertSame(
            ["UPDATE fe_users SET name = 'O''Brien';"],
            $this->driver->anonymizeStatements([
                new AnonymizationRule('fe_users', 'name', AnonymizationStrategy::StaticValue, "O'Brien"),
            ]),
        );
    }

    #[Test]
    public function hasNothingToRunWithoutRules(): void
    {
        self::assertSame([], $this->driver->anonymizeStatements([]));
    }

    private function db(): DatabaseConfig
    {
        return new DatabaseConfig(
            name: 'app',
            host: 'db',
            user: 'u',
            password: 'p',
            port: 5432,
            type: DatabaseSystem::PostgreSQL,
        );
    }
}
