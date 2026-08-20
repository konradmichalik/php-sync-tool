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
use KonradMichalik\SyncTool\Database\ClientBinaries;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;

/**
 * DriverFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class DriverFactory
{
    /**
     * @param array<string, string> $console per-endpoint binary path overrides
     */
    public function forDatabase(DatabaseConfig $db, array $console = []): DatabaseDriver
    {
        $binaries = ClientBinaries::resolve($db->type, $console);

        return match ($db->type) {
            DatabaseSystem::MySQL, DatabaseSystem::MariaDB => new MysqlDriver(binaries: $binaries),
            DatabaseSystem::PostgreSQL => new PostgresDriver(binaries: $binaries),
        };
    }
}
