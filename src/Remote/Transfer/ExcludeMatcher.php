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
}
