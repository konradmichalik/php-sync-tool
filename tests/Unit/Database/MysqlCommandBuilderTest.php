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
            'mysqldump --defaults-extra-file=/tmp/.my_x.cnf --single-transaction --quick --extended-insert '
            .'--no-tablespaces mydb --ignore-table=mydb.cache users orders | gzip > /tmp/_mydb_2026.sql.gz',
            $command,
        );
    }

    #[Test]
    public function importCommandStreamsGzip(): void
    {
        self::assertSame(
            'gunzip -c /tmp/dump.sql.gz | mysql --defaults-extra-file=/tmp/.my.cnf mydb',
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
    public function showTablesLikeEscapesPatternQuotes(): void
    {
        self::assertSame(
            "SHOW TABLES FROM `mydb` LIKE 'cache_%';",
            $this->builder->showTablesLikeSql('mydb', 'cache_%'),
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
