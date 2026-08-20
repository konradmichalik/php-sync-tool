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
    public function execCommandEscapesDoubleQuotesBackticksAndBackslashes(): void
    {
        $command = $this->builder->execCommand('mysql', '--defaults-extra-file=/tmp/.my.cnf', 'mydb', 'SELECT "a", `col`, \\x;');

        self::assertSame(
            'mysql --defaults-extra-file=/tmp/.my.cnf mydb -e "SELECT \\"a\\", \\`col\\`, \\\\x;"',
            $command,
        );
    }

    #[Test]
    public function showTablesLikeEscapesPatternQuotes(): void
    {
        self::assertSame(
            "SHOW TABLES FROM `mydb` LIKE 'cache_%';",
            $this->builder->showTablesLikeSql('mydb', 'cache_%'),
        );
    }
}
