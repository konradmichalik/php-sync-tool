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

namespace MoveElevator\DbSyncTool\Security;


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
}
