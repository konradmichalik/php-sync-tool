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
use KonradMichalik\SyncTool\Security\Shell;

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
     * An override is a path to a binary, as documented, and is quoted as one
     * shell argument. Interpolating it raw made `console` the one config key that
     * could still run a command of its own choosing on a remote endpoint. An
     * ordinary path needs no quoting and is passed through unchanged; a wrapper
     * that relies on being split into several arguments belongs in a script whose
     * path is given here.
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

        return new self(
            Shell::quote($console[$client] ?? $client),
            Shell::quote($console[$dump] ?? $dump),
        );
    }
}
