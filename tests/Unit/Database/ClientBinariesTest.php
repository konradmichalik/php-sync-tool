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

use KonradMichalik\SyncTool\Database\ClientBinaries;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ClientBinariesTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ClientBinariesTest extends TestCase
{
    #[Test]
    public function mysqlKeepsTheMysqlNames(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::MySQL);

        self::assertSame('mysql', $binaries->client);
        self::assertSame('mysqldump', $binaries->dump);
    }

    /**
     * MariaDB 11 deprecated the `mysql` and `mysqldump` symlinks, so a MariaDB
     * endpoint has to be addressed by its own names.
     */
    #[Test]
    public function mariadbUsesItsOwnNames(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::MariaDB);

        self::assertSame('mariadb', $binaries->client);
        self::assertSame('mariadb-dump', $binaries->dump);
    }

    #[Test]
    public function postgresUsesPsqlAndPgDump(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::PostgreSQL);

        self::assertSame('psql', $binaries->client);
        self::assertSame('pg_dump', $binaries->dump);
    }

    #[Test]
    public function consoleOverridesTheBinaryItNames(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::MySQL, [
            'mysql' => '/usr/local/mysql/bin/mysql',
            'mysqldump' => '/usr/local/mysql/bin/mysqldump',
        ]);

        self::assertSame('/usr/local/mysql/bin/mysql', $binaries->client);
        self::assertSame('/usr/local/mysql/bin/mysqldump', $binaries->dump);
    }

    #[Test]
    public function anOverrideForAnotherSystemIsIgnored(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::MariaDB, ['mysqldump' => '/opt/mysqldump']);

        self::assertSame('mariadb-dump', $binaries->dump, 'a MariaDB endpoint is not configured through the mysql keys');
    }

    #[Test]
    public function unrelatedConsoleEntriesAreLeftAlone(): void
    {
        $binaries = ClientBinaries::resolve(DatabaseSystem::MySQL, ['php' => '/usr/bin/php8.3']);

        self::assertSame('mysql', $binaries->client);
        self::assertSame('mysqldump', $binaries->dump);
    }
}
