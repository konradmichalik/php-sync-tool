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

namespace KonradMichalik\SyncTool\Command;

use function count;
use function str_starts_with;

/**
 * LegacyShortOptions.
 *
 * db-sync-tool's `-kd <dir>` combined "keep the dump" with "put it here",
 * which php-sync-tool splits across two long options (`--keep-dump` and the
 * existing `--target-dump-dir`). Symfony Console's shortcut mechanism can't
 * express that as one flag, so the rewrite happens on the raw argv, before
 * Symfony ever parses it.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LegacyShortOptions
{
    /**
     * @param list<string> $argv the full process argv, script name included
     *
     * @return list<string>
     */
    public static function rewrite(array $argv): array
    {
        $result = [];
        $count = count($argv);

        for ($i = 0; $i < $count; ++$i) {
            $token = $argv[$i];

            if ('--' === $token) {
                // Symfony Console itself stops interpreting options after a bare
                // `--` (ArgvInput::parseToken()); a legacy flag spelled out past
                // that point is a literal argument, not ours to rewrite.
                for (; $i < $count; ++$i) {
                    $result[] = $argv[$i];
                }
                break;
            }

            if ('-dn' === $token) {
                $result[] = '--dump-name';
                continue;
            }

            if ('-kd' === $token) {
                $result[] = '--keep-dump';
                $next = $argv[$i + 1] ?? null;
                if (null !== $next && !str_starts_with($next, '-')) {
                    $result[] = '--target-dump-dir';
                    $result[] = $next;
                    ++$i;
                }
                continue;
            }

            $result[] = $token;
        }

        return $result;
    }
}
