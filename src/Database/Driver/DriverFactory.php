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

namespace KonradMichalik\SyncTool\Database\Driver;

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\{ClientBinaries, ServerProbe};
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use KonradMichalik\SyncTool\Remote\CommandRunner;

/**
 * DriverFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class DriverFactory
{
    public function __construct(
        private ServerProbe $probe = new ServerProbe(),
    ) {}

    /**
     * Given a runner, the endpoint is asked which binaries it actually has rather
     * than being taken at the configuration's word.
     *
     * @param array<string, string> $console per-endpoint binary path overrides
     */
    public function forDatabase(DatabaseConfig $db, array $console = [], ?CommandRunner $runner = null): DatabaseDriver
    {
        $system = null === $runner ? $db->type : $this->probe->clientFamily($db->type, $console, $runner);
        $binaries = ClientBinaries::resolve($system, $console);

        return match ($system) {
            DatabaseSystem::MySQL, DatabaseSystem::MariaDB => new MysqlDriver(binaries: $binaries),
            DatabaseSystem::PostgreSQL => new PostgresDriver(binaries: $binaries),
        };
    }
}
