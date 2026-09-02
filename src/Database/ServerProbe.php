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

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\Driver\DatabaseDriver;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use KonradMichalik\SyncTool\Remote\CommandRunner;
use KonradMichalik\SyncTool\Security\Shell;
use KonradMichalik\SyncTool\Util\Pure;
use Throwable;

use function preg_match;
use function sprintf;
use function str_contains;
use function trim;

/**
 * ServerProbe.
 *
 * What the endpoint actually has, as opposed to what the configuration assumed.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ServerProbe
{
    /**
     * Which of the two MySQL-family naming schemes the endpoint actually carries.
     *
     * MariaDB 11 dropped the `mysql` and `mysqldump` symlinks, so an endpoint
     * running it has no binary under the name a default configuration asks for,
     * and the dump failed with "command not found" unless `type: mariadb` had been
     * written out by hand.
     *
     * Availability decides rather than declaration, which needs no way of telling
     * an unset `type` from an explicit `mysql`. A configured name that is present
     * always wins, so MariaDB 10, which ships both, keeps whatever was asked for.
     *
     * @param array<string, string> $console per-endpoint binary path overrides
     */
    public function clientFamily(DatabaseSystem $configured, array $console, CommandRunner $runner): DatabaseSystem
    {
        if (DatabaseSystem::PostgreSQL === $configured || [] !== $console) {
            // An endpoint that names its own binaries has already answered this.
            return $configured;
        }

        $other = DatabaseSystem::MySQL === $configured ? DatabaseSystem::MariaDB : DatabaseSystem::MySQL;

        $answer = trim($runner->run(sprintf(
            'command -v %s >/dev/null 2>&1 && echo configured || { command -v %s >/dev/null 2>&1 && echo other; }',
            Shell::quote(ClientBinaries::dumpBinaryFor($configured)),
            Shell::quote(ClientBinaries::dumpBinaryFor($other)),
        ), true));

        return 'other' === $answer ? $other : $configured;
    }

    /**
     * The version string the server reports, or null when it cannot be read.
     *
     * Null is a valid answer: the caller uses it to decide options, and a failed
     * probe must not fail the sync over a question that only refines a command.
     */
    public function version(DatabaseDriver $driver, DatabaseConfig $db, string $credentialsPath, CommandRunner $runner): ?string
    {
        try {
            $output = $runner->run($driver->execCommand($db, $credentialsPath, $driver->versionQuery()), true);
        } catch (Throwable) {
            return null;
        }

        $lines = Pure::outputLines($output);

        foreach ($lines as $line) {
            // `mysql -e` prints a header row; `psql -t -A` does not. Matching the
            // shape of a version rather than a line number covers both.
            if (1 === preg_match('/^\d+\.\d+/', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * How the version reads to a person: "MariaDB 11.4.2" rather than
     * "11.4.2-MariaDB-1:11.4.2+maria~ubu2404".
     */
    public function describe(DatabaseSystem $system, string $version): string
    {
        $name = str_contains($version, 'MariaDB') ? DatabaseSystem::MariaDB->value : $system->value;

        return 1 === preg_match('/^(\d+(?:\.\d+)*)/', $version, $matches)
            ? sprintf('%s %s', $name, $matches[1])
            : sprintf('%s %s', $name, $version);
    }
}
