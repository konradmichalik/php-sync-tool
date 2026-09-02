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

namespace KonradMichalik\SyncTool\Util;

use function is_scalar;
use function is_string;

/**
 * Pure.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Pure
{
    /**
     * The non-empty lines of a command's output, trimmed and renumbered.
     *
     * Every reader of command output wants exactly this, because a client pads
     * its result with a trailing newline and, depending on the client, a header
     * row and surrounding whitespace.
     *
     * @return list<string>
     */
    public static function outputLines(string $output): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn (string $line): bool => '' !== $line,
        ));
    }

    /**
     * Extract the first version-like token from a tool's --version output.
     * The regex is intentionally identical to the Python original (including
     * its `=?` quirks) to keep extraction byte-compatible.
     */
    public static function parseVersion(?string $output): ?string
    {
        if (null === $output || '' === $output) {
            return null;
        }

        if (1 === preg_match('/\d+(=?\.(\d+(=?\.(\d+)*)*)*)*/', $output, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Strip a single pair of matching outer quotes (both " or both '),
     * leaving inner quotes and non-string values untouched.
     */
    public static function removeSurroundingQuotes(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Apply removeSurroundingQuotes() to every value of a config map,
     * leaving keys and non-string values unchanged.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function cleanDbConfig(array $config): array
    {
        return array_map(self::removeSurroundingQuotes(...), $config);
    }

    /**
     * Convert an associative options map to CLI arguments. true → flag,
     * false/null → skipped, anything else → "--key value". Empty → null.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>|null
     */
    public static function dictToArgs(array $data): ?array
    {
        $args = [];

        foreach ($data as $key => $value) {
            if (true === $value) {
                $args[] = '--'.$key;
            } elseif (false !== $value && null !== $value) {
                $args[] = '--'.$key;
                $args[] = is_scalar($value) ? (string) $value : '';
            }
        }

        return [] === $args ? null : $args;
    }

    /**
     * Sequentially remove every given substring (all occurrences) from a string.
     *
     * @param list<string> $elements
     */
    public static function removeMultipleElementsFromString(array $elements, string $string): string
    {
        foreach ($elements as $element) {
            $string = str_replace($element, '', $string);
        }

        return $string;
    }

    public static function getFileFromPath(string $path): string
    {
        return basename($path);
    }
}
