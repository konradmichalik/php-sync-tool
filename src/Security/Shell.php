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

namespace KonradMichalik\SyncTool\Security;

use function sprintf;

/**
 * Shell.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Shell
{
    /**
     * Safely quote a value for use as a single shell argument, preventing
     * command injection. Mirrors Python's shlex.quote() byte-for-byte:
     * only values containing a character outside the safe set are wrapped
     * in single quotes (with embedded single quotes escaped).
     */
    public static function quote(int|float|string|null $arg): string
    {
        if (null === $arg) {
            return "''";
        }

        $value = (string) $arg;

        if ('' === $value) {
            return "''";
        }

        if (1 !== preg_match('/[^\w@%+=:,.\/-]/', $value)) {
            return $value;
        }

        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    /**
     * A pipeline that fails when *either* stage fails.
     *
     * A plain `mysqldump … | gzip > dump.gz` reports the exit status of `gzip`,
     * so a dump that aborted halfway through (wrong credentials, killed query,
     * full disk) looked like a success and left a valid but useless archive
     * behind, which the next step then transferred and imported over a database.
     *
     * The producer's status travels out on file descriptor 3 while its stdout
     * stays on the pipe; the assignment keeps the pipeline's own status, so the
     * `&&` covers the consumer. POSIX-only constructs, verified against sh,
     * bash, dash and zsh (hence `sync_status`: `status` is read-only in zsh).
     *
     * The consumer must not write to stdout: it shares the channel that carries
     * the status. Every caller either redirects it to a file or discards it.
     */
    public static function strictPipe(string $producer, string $consumer): string
    {
        return sprintf(
            'sync_status=$( { { %s; echo $? >&3; } | %s; } 3>&1 ) && [ "$sync_status" -eq 0 ]',
            $producer,
            $consumer,
        );
    }
}
