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

namespace KonradMichalik\SyncTool\Tests\Unit\Database;

use KonradMichalik\SyncTool\Database\MysqlCommandBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MysqlCommandBuilderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlCommandBuilderTest extends TestCase
{
    private MysqlCommandBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MysqlCommandBuilder();
    }

    #[Test]
    public function dumpOptionsBaseIncludesNoTablespaces(): void
    {
        self::assertSame(
            '--single-transaction --quick --extended-insert --no-tablespaces ',
            $this->builder->dumpOptions(),
        );
    }

    #[Test]
    public function dumpOptionsAppendsWhereAndAdditional(): void
    {
        self::assertSame(
            "--single-transaction --quick --extended-insert --no-tablespaces --where='id > 5' --hex-blob ",
            $this->builder->dumpOptions('id > 5', '--hex-blob'),
        );
    }

    /**
     * The option only arrived in MySQL 5.6, so on anything older mysqldump rejects
     * the very command it was added to make work.
     */
    #[Test]
    public function dumpOptionsDropNoTablespacesForAServerTooOldToKnowIt(): void
    {
        self::assertSame(
            '--single-transaction --quick --extended-insert ',
            $this->builder->dumpOptions(serverVersion: '5.5.62-log'),
        );
    }

    #[Test]
    public function dumpOptionsKeepNoTablespacesForAModernServer(): void
    {
        self::assertStringContainsString('--no-tablespaces', $this->builder->dumpOptions(serverVersion: '8.0.36'));
    }

    #[Test]
    public function dumpOptionsKeepNoTablespacesForMariadb(): void
    {
        self::assertStringContainsString('--no-tablespaces', $this->builder->dumpOptions(serverVersion: '11.4.2-MariaDB'));
    }

    #[Test]
    public function dumpCommandStreamsToGzip(): void
    {
        $command = $this->builder->dumpCommand(
            'mysqldump',
            '--defaults-extra-file=/tmp/.my_x.cnf',
            '--single-transaction --quick --extended-insert --no-tablespaces ',
            'mydb',
            '--ignore-table=mydb.cache',
            ['users', 'orders'],
            'gzip',
            '/tmp/_mydb_2026.sql',
        );

        self::assertSame(
            'sync_status=$( { { mysqldump --defaults-extra-file=/tmp/.my_x.cnf --single-transaction --quick --extended-insert '
            .'--no-tablespaces mydb --ignore-table=mydb.cache users orders; echo $? >&3; } '
            .'| gzip > /tmp/_mydb_2026.sql.gz; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $command,
        );
    }

    #[Test]
    public function importCommandStreamsGzip(): void
    {
        self::assertSame(
            'sync_status=$( { { gunzip -c /tmp/dump.sql.gz; echo $? >&3; } '
            .'| mysql --defaults-extra-file=/tmp/.my.cnf mydb > /dev/null; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $this->builder->importCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', 'gunzip', '/tmp/dump.sql.gz'),
        );
    }

    #[Test]
    public function importCommandRedirectsPlainSql(): void
    {
        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my.cnf mydb < /tmp/dump.sql',
            $this->builder->importCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', 'gunzip', '/tmp/dump.sql'),
        );
    }

    #[Test]
    public function execCommandPassesSqlAsASingleQuotedArgument(): void
    {
        $command = $this->builder->execCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', 'SELECT "a", `col`, \\x;');

        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my.cnf mydb -e \'SELECT "a", `col`, \\x;\'',
            $command,
        );
    }

    #[Test]
    public function execCommandKeepsSqlStringLiteralsIntact(): void
    {
        $command = $this->builder->execCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', "UPDATE t SET v = 'x';");

        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my.cnf mydb -e \'UPDATE t SET v = \'"\'"\'x\'"\'"\';\'',
            $command,
        );
    }

    /**
     * `$(…)` and `$VAR` stay live inside a double-quoted shell argument, which
     * made every SQL-carrying config key a command-execution path.
     */
    #[Test]
    public function execCommandDoesNotLetTheShellExpandSql(): void
    {
        $command = $this->builder->execCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', 'UPDATE t SET v = "$(id -un)" AND w = "${HOME}";');

        self::assertStringNotContainsString('-e "', $command);
        self::assertSame('UPDATE t SET v = "$(id -un)" AND w = "${HOME}";', self::shellEvaluate($command));
    }

    #[Test]
    public function showTablesMatchingEscapesPatternAndDatabaseQuotes(): void
    {
        self::assertSame(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'my''db' "
            ."AND (TABLE_NAME LIKE 'cache_%') ORDER BY TABLE_NAME;",
            $this->builder->showTablesMatchingSql("my'db", ['cache_%']),
        );
    }

    /**
     * Every pattern is answered by one statement, so a list of them costs one
     * round trip instead of one per entry.
     */
    #[Test]
    public function showTablesMatchingCombinesEveryPatternIntoOneStatement(): void
    {
        self::assertSame(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'mydb' "
            ."AND (TABLE_NAME LIKE 'cache_%' OR TABLE_NAME LIKE 'cf_%' OR TABLE_NAME LIKE 'sys_log%') "
            .'ORDER BY TABLE_NAME;',
            $this->builder->showTablesMatchingSql('mydb', ['cache_%', 'cf_%', 'sys_log%']),
        );
    }

    /**
     * Hands the built command to a real shell and reports what arrived in the
     * `-e` argument, so the assertion covers shell semantics and not just our
     * own idea of escaping.
     */
    private static function shellEvaluate(string $command): string
    {
        $probe = str_replace('mysql --defaults-extra-file=/tmp/.my.cnf mydb -e ', 'printf %s ', $command);

        return (string) shell_exec($probe);
    }
}
