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

namespace KonradMichalik\SyncTool\Database;

use KonradMichalik\SyncTool\Enum\DatabaseSystem;

/**
 * ClientBinaries.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ClientBinaries
{
    public function __construct(
        public string $client = 'mysql',
        public string $dump = 'mysqldump',
    ) {}

    /**
     * The two binaries an endpoint needs, named for its database system and
     * overridable per endpoint through `console`.
     *
     * MariaDB 11 deprecated the `mysqldump` and `mysql` symlinks, so a MariaDB
     * endpoint is addressed by its own names. An override key is the default
     * binary name it replaces, which keeps the documented `mysql` and `mysqldump`
     * keys working and needs no separate spelling per system.
     *
     * @param array<string, string> $console
     */
    public static function resolve(DatabaseSystem $system, array $console = []): self
    {
        [$client, $dump] = match ($system) {
            DatabaseSystem::MySQL => ['mysql', 'mysqldump'],
            DatabaseSystem::MariaDB => ['mariadb', 'mariadb-dump'],
            DatabaseSystem::PostgreSQL => ['psql', 'pg_dump'],
        };

        return new self($console[$client] ?? $client, $console[$dump] ?? $dump);
    }
}
