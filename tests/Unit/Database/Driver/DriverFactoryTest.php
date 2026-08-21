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

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\Driver\{DriverFactory, MysqlDriver, PostgresDriver};
use KonradMichalik\SyncTool\Database\DumpRequest;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DriverFactoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DriverFactoryTest extends TestCase
{
    #[Test]
    public function picksTheMysqlDriverForMysqlAndMariadb(): void
    {
        $factory = new DriverFactory();

        self::assertInstanceOf(MysqlDriver::class, $factory->forDatabase(new DatabaseConfig(name: 'app')));
        self::assertInstanceOf(
            MysqlDriver::class,
            $factory->forDatabase(new DatabaseConfig(name: 'app', type: DatabaseSystem::MariaDB)),
        );
    }

    #[Test]
    public function picksThePostgresDriverForPostgres(): void
    {
        self::assertInstanceOf(
            PostgresDriver::class,
            (new DriverFactory())->forDatabase(new DatabaseConfig(name: 'app', type: DatabaseSystem::PostgreSQL)),
        );
    }

    #[Test]
    public function aMariadbEndpointGetsTheMariadbBinaries(): void
    {
        $driver = (new DriverFactory())->forDatabase(new DatabaseConfig(name: 'app', type: DatabaseSystem::MariaDB));

        self::assertStringContainsString(
            'mariadb-dump',
            $driver->dumpCommand(new DumpRequest(db: new DatabaseConfig(name: 'app'), credentialsPath: '/tmp/c', dumpFilePath: '/tmp/d.sql')),
        );
        self::assertStringContainsString(
            'mariadb ',
            $driver->importCommand(new DatabaseConfig(name: 'app'), '/tmp/c', '/tmp/d.sql'),
        );
    }

    #[Test]
    public function consoleOverridesReachTheGeneratedCommands(): void
    {
        $driver = (new DriverFactory())->forDatabase(
            new DatabaseConfig(name: 'app'),
            ['mysqldump' => '/opt/bin/mysqldump'],
        );

        self::assertStringContainsString(
            '{ /opt/bin/mysqldump',
            $driver->dumpCommand(new DumpRequest(db: new DatabaseConfig(name: 'app'), credentialsPath: '/tmp/c', dumpFilePath: '/tmp/d.sql')),
        );
    }
}
