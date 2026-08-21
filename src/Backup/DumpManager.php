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

namespace KonradMichalik\SyncTool\Backup;

use KonradMichalik\SyncTool\Security\Shell;

use function array_slice;
use function sprintf;

/**
 * DumpManager.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DumpManager
{
    /**
     * @param list<string> $filesNewestFirst
     *
     * @return list<string> the files to delete (everything past the newest $keep)
     */
    public function filesToRemove(array $filesNewestFirst, int $keep): array
    {
        return array_slice($filesNewestFirst, max(0, $keep));
    }

    /**
     * Build the "stat | sort -rn | grep" listing command (newest first),
     * choosing the BSD (Darwin) or GNU stat format.
     *
     * Both formats report the modification time as epoch seconds, because that is
     * the only thing `sort -rn` can actually order: a formatted date sorts by the
     * number it happens to start with, which is the year on GNU and nothing at all
     * on BSD, so retention used to drop dumps in an arbitrary order.
     *
     * `$pathPrefix` is one shell argument and the glob star is appended outside the
     * quotes, so the shell still expands it while a dump directory carrying spaces
     * or shell syntax cannot break out of the command.
     */
    public function listDumpsCommand(
        string $statBin,
        string $sortBin,
        string $grepBin,
        string $pathPrefix,
        bool $isDarwin,
    ): string {
        $format = $isDarwin ? '-f "%m %N"' : '-c "%Y %n"';

        return sprintf(
            '%s %s %s* | %s -rn | %s -E "\\.gz$|\\.sql$"',
            $statBin,
            $format,
            Shell::quote($pathPrefix),
            $sortBin,
            $grepBin,
        );
    }

    public function extractFilename(string $line): string
    {
        $position = strrpos($line, ' ');

        return false === $position ? $line : substr($line, $position + 1);
    }
}
