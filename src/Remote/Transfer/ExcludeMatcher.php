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

namespace KonradMichalik\SyncTool\Remote\Transfer;

use function explode;
use function fnmatch;

/**
 * ExcludeMatcher.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ExcludeMatcher
{
    /**
     * @param list<string> $patterns
     *
     * Case-sensitive on POSIX (the only supported platform for this tool)
     */
    public static function matches(string $relativePath, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if the path itself matches, OR any ancestor directory segment
     * matches — mirrors rsync's behavior of not descending into an excluded
     * directory, so everything beneath it is implicitly excluded too.
     *
     * @param list<string> $patterns
     */
    public static function isPathExcluded(string $relativePath, array $patterns): bool
    {
        $segments = explode('/', $relativePath);
        $path = '';

        foreach ($segments as $segment) {
            $path = '' === $path ? $segment : $path.'/'.$segment;

            if (self::matches($path, $patterns) || self::matches($segment, $patterns)) {
                return true;
            }
        }

        return false;
    }
}
