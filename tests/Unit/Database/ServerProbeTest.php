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

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\Driver\MysqlDriver;
use KonradMichalik\SyncTool\Database\ServerProbe;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use KonradMichalik\SyncTool\Tests\Fixture\RecordingCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ServerProbeTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ServerProbeTest extends TestCase
{
    /**
     * MariaDB 11 dropped the `mysqldump` symlink, so a default configuration named
     * a binary the endpoint does not have.
     */
    #[Test]
    public function switchesToMariadbNamesWhenTheMysqlOnesAreAbsent(): void
    {
        $system = (new ServerProbe())->clientFamily(
            DatabaseSystem::MySQL,
            [],
            new RecordingCommandRunner(['command -v' => 'other']),
        );

        self::assertSame(DatabaseSystem::MariaDB, $system);
    }

    #[Test]
    public function keepsTheConfiguredNamesWhenTheyArePresent(): void
    {
        $system = (new ServerProbe())->clientFamily(
            DatabaseSystem::MySQL,
            [],
            new RecordingCommandRunner(['command -v' => 'configured']),
        );

        self::assertSame(DatabaseSystem::MySQL, $system);
    }

    #[Test]
    public function keepsTheConfiguredNamesWhenNeitherAnswers(): void
    {
        $system = (new ServerProbe())->clientFamily(
            DatabaseSystem::MariaDB,
            [],
            new RecordingCommandRunner(['command -v' => '']),
        );

        self::assertSame(DatabaseSystem::MariaDB, $system);
    }

    /**
     * An endpoint that names its own binaries through `console` has already said
     * what it has, so there is nothing to ask.
     */
    #[Test]
    public function doesNotProbeAnEndpointThatNamesItsOwnBinaries(): void
    {
        $runner = new RecordingCommandRunner();

        (new ServerProbe())->clientFamily(DatabaseSystem::MySQL, ['mysqldump' => '/opt/bin/mysqldump'], $runner);

        self::assertSame([], $runner->commands);
    }

    #[Test]
    public function doesNotProbeAPostgresEndpoint(): void
    {
        $runner = new RecordingCommandRunner();

        (new ServerProbe())->clientFamily(DatabaseSystem::PostgreSQL, [], $runner);

        self::assertSame([], $runner->commands);
    }

    #[Test]
    public function readsTheVersionPastTheHeaderRowMysqlPrints(): void
    {
        $version = $this->version("VERSION()\n11.4.2-MariaDB-1:11.4.2+maria~ubu2404");

        self::assertSame('11.4.2-MariaDB-1:11.4.2+maria~ubu2404', $version);
    }

    #[Test]
    public function reportsNoVersionWhenTheServerSaysNothingUsable(): void
    {
        self::assertNull($this->version("VERSION()\n"));
    }

    #[Test]
    public function describesMariadbByItsOwnNameEvenUnderTheMysqlDriver(): void
    {
        self::assertSame(
            'MariaDB 11.4.2',
            (new ServerProbe())->describe(DatabaseSystem::MySQL, '11.4.2-MariaDB-1:11.4.2+maria~ubu2404'),
        );
    }

    #[Test]
    public function describesMysqlByTheDriversName(): void
    {
        self::assertSame('MySQL 8.0.36', (new ServerProbe())->describe(DatabaseSystem::MySQL, '8.0.36'));
    }

    private function version(string $output): ?string
    {
        return (new ServerProbe())->version(
            new MysqlDriver(),
            new DatabaseConfig(name: 'app'),
            '/tmp/.my_x.cnf',
            new RecordingCommandRunner(['SELECT VERSION()' => $output]),
        );
    }
}
