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

namespace MoveElevator\DbSyncTool\Config;

use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * ConfigAccessor.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigAccessor
{
    /**
     * @param array<string, mixed> $data
     */
    public static function get(array $data, string $key, mixed $default): mixed
    {
        $value = $data[$key] ?? null;

        return $value ?? $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function getString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function getStringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function getBool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;

        return null === $value ? $default : (bool) $value;
    }

    /**
     * Mirrors Python int(): integers and integer-like strings convert,
     * anything else (floats strings, garbage, null) falls back to default.
     *
     * @param array<string, mixed> $data
     */
    public static function getInt(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && 1 === preg_match('/^[+-]?\d+$/', trim($value))) {
            return (int) trim($value);
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function getIntOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<mixed>
     */
    public static function getList(array $data, string $key, ?string $fallbackKey = null): array
    {
        $value = $data[$key] ?? null;

        if (null !== $value) {
            return is_array($value) ? array_values($value) : [];
        }

        if (null !== $fallbackKey) {
            $fallback = $data[$fallbackKey] ?? null;

            return is_array($fallback) ? array_values($fallback) : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public static function getStringList(array $data, string $key, ?string $fallbackKey = null): array
    {
        return array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            self::getList($data, $key, $fallbackKey),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    public static function getStringMap(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $mapKey => $mapValue) {
            $result[(string) $mapKey] = is_scalar($mapValue) ? (string) $mapValue : '';
        }

        return $result;
    }
}
