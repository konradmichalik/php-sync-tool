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

use KonradMichalik\SyncTool\Config\{DatabaseConfig, SyncConfig};
use KonradMichalik\SyncTool\Database\Driver\PostgresDriver;
use KonradMichalik\SyncTool\Database\DumpRequest;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
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
            'PGPASSFILE=/tmp/.pgpass_ab12 pg_dump --no-owner --no-privileges --clean --if-exists'
            .' -h db -p 5432 -U u -d app | gzip > /tmp/app.sql.gz',
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
            'gunzip -c /tmp/app.sql.gz | PGPASSFILE=/tmp/.pgpass_ab12 psql -v ON_ERROR_STOP=1 -q -h db -p 5432 -U u -d app',
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
            $this->driver->listTablesSql('app'),
        );
        self::assertSame(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'cache_%';",
            $this->driver->listTablesLikeSql('app', 'cache_%'),
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

        self::assertSame(['where', 'additional_mysqldump_options'], $this->driver->unsupportedFeatures($config));
    }

    #[Test]
    public function reportsNothingUnsupportedForAPlainSync(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'type' => 'postgres']],
        ]);

        self::assertSame([], $this->driver->unsupportedFeatures($config));
    }

    #[Test]
    public function writesAPgpassLineAndARandomPath(): void
    {
        self::assertSame("db:5432:app:u:p\n", $this->driver->credentialsContent($this->db()));
        self::assertMatchesRegularExpression('#^/tmp/\.pgpass_[0-9a-f]{16}$#', $this->driver->credentialsPath());
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
