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

namespace MoveElevator\DbSyncTool\Backup;

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
     */
    public function listDumpsCommand(
        string $statBin,
        string $sortBin,
        string $grepBin,
        string $path,
        bool $isDarwin,
    ): string {
        $format = $isDarwin ? '-f "%Sm %N"' : '-c "%y %n"';

        return sprintf('%s %s %s | %s -rn | %s -E "\\.gz$|\\.sql$"', $statBin, $format, $path, $sortBin, $grepBin);
    }

    public function extractFilename(string $line): string
    {
        $position = strrpos($line, ' ');

        return false === $position ? $line : substr($line, $position + 1);
    }
}
