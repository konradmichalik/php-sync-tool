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
use KonradMichalik\SyncTool\Database\Driver\MysqlDriver;
use KonradMichalik\SyncTool\Database\DumpRequest;
use KonradMichalik\SyncTool\Enum\{AnonymizationStrategy, DatabaseSystem};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MysqlDriverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlDriverTest extends TestCase
{
    private MysqlDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new MysqlDriver();
    }

    #[Test]
    public function reportsItsSystem(): void
    {
        self::assertSame(DatabaseSystem::MySQL, $this->driver->system());
    }

    #[Test]
    public function buildsTheSameDumpCommandTheToolUsedBefore(): void
    {
        $command = $this->driver->dumpCommand(new DumpRequest(
            new DatabaseConfig(name: 'app'),
            '/tmp/.my_ab12.cnf',
            '/tmp/app.sql',
        ));

        self::assertSame(
            'sync_status=$( { { mysqldump --defaults-extra-file=/tmp/.my_ab12.cnf --single-transaction --quick '
            .'--extended-insert --no-tablespaces app ; echo $? >&3; } | gzip > /tmp/app.sql.gz; } 3>&1 ) '
            .'&& [ "$sync_status" -eq 0 ]',
            $command,
        );
    }

    #[Test]
    public function rendersIgnoreTablesAsMysqldumpOptions(): void
    {
        $command = $this->driver->dumpCommand(new DumpRequest(
            new DatabaseConfig(name: 'app'),
            '/tmp/.my_ab12.cnf',
            '/tmp/app.sql',
            ignoreTables: ['cache_pages', 'sys_log'],
        ));

        self::assertStringContainsString('app --ignore-table=app.cache_pages --ignore-table=app.sys_log', $command);
    }

    #[Test]
    public function rendersExportTablesWhereClauseAndExtraOptions(): void
    {
        $command = $this->driver->dumpCommand(new DumpRequest(
            new DatabaseConfig(name: 'app'),
            '/tmp/.my_ab12.cnf',
            '/tmp/app.sql',
            exportTables: ['users'],
            where: 'id > 1',
            additionalOptions: '--skip-lock-tables',
        ));

        self::assertSame(
            'sync_status=$( { { mysqldump --defaults-extra-file=/tmp/.my_ab12.cnf --single-transaction --quick '
            ."--extended-insert --no-tablespaces --where='id > 1' --skip-lock-tables app  users; echo $? >&3; } "
            .'| gzip > /tmp/app.sql.gz; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $command,
        );
    }

    #[Test]
    public function importsAGzippedDumpThroughGunzip(): void
    {
        self::assertSame(
            'sync_status=$( { { gunzip -c /tmp/app.sql.gz; echo $? >&3; } '
            .'| mysql --defaults-extra-file=/tmp/.my_ab12.cnf app > /dev/null; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $this->driver->importCommand(new DatabaseConfig(name: 'app'), '/tmp/.my_ab12.cnf', '/tmp/app.sql.gz'),
        );
    }

    #[Test]
    public function importsAPlainDumpByRedirection(): void
    {
        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my_ab12.cnf app < /tmp/app.sql',
            $this->driver->importCommand(new DatabaseConfig(name: 'app'), '/tmp/.my_ab12.cnf', '/tmp/app.sql'),
        );
    }

    #[Test]
    public function executesSqlWithTheDatabaseSelected(): void
    {
        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my_ab12.cnf app -e \'SELECT 1\'',
            $this->driver->execCommand(new DatabaseConfig(name: 'app'), '/tmp/.my_ab12.cnf', 'SELECT 1'),
        );
    }

    #[Test]
    public function listsTablesAndStripsTheHeaderRowFromTheOutput(): void
    {
        self::assertSame('SHOW TABLES;', $this->driver->listTablesSql());
        self::assertSame(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'app' "
            ."AND (TABLE_NAME LIKE 'cache_%') ORDER BY TABLE_NAME;",
            $this->driver->listTablesMatchingSql('app', ['cache_%']),
        );
        self::assertSame(
            ['users', 'posts'],
            $this->driver->parseTableList("Tables_in_app\nusers\nposts\n"),
        );
    }

    #[Test]
    public function batchesDropAndTruncateWithForeignKeyChecksDisabled(): void
    {
        self::assertSame(
            'SET FOREIGN_KEY_CHECKS = 0; DROP TABLE `users`; DROP TABLE `posts`; SET FOREIGN_KEY_CHECKS = 1;',
            $this->driver->dropTablesStatement(['users', 'posts']),
        );
        self::assertSame(
            'SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE `sys_log`; SET FOREIGN_KEY_CHECKS = 1;',
            $this->driver->truncateTablesStatement(['sys_log']),
        );
        self::assertNull($this->driver->dropTablesStatement([]));
        self::assertNull($this->driver->truncateTablesStatement([]));
    }

    #[Test]
    public function supportsEveryConfiguredFeature(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
            'where' => 'id > 1',
        ]);

        self::assertSame([], $this->driver->unsupportedFeatures($config, $config->origin->db));
    }

    #[Test]
    public function writesACredentialFileWithTheCredentialsInIt(): void
    {
        $content = $this->driver->credentialsContent(new DatabaseConfig(name: 'app', host: 'db', user: 'u', password: 'p', port: 3306));

        self::assertStringContainsString('[client]', $content);
        self::assertStringContainsString('user=u', $content);
        self::assertStringContainsString('password="p"', $content);
        self::assertMatchesRegularExpression('#^/tmp/\.my_[0-9a-f]{16}\.cnf$#', $this->driver->credentialsPath());
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
            "UPDATE `fe_users` SET `email` = CONCAT(MD5(`email`), '@example.invalid');",
            'UPDATE `fe_users` SET `password` = MD5(`password`);',
            "UPDATE `fe_users` SET `name` = 'Redacted';",
            'UPDATE `sys_log` SET `details` = NULL;',
        ], $statements);
    }

    #[Test]
    public function escapesASingleQuoteInAStaticValue(): void
    {
        self::assertSame(
            ["UPDATE `fe_users` SET `name` = 'O''Brien';"],
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
}
