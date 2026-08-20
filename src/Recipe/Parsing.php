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

namespace KonradMichalik\SyncTool\Recipe;

use KonradMichalik\SyncTool\Exception\ParsingException;
use KonradMichalik\SyncTool\Util\Pure;
use OutOfBoundsException;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

/**
 * Parsing.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Parsing
{
    /**
     * @return array{db_type: string, user: string, password: string, host: string, port: string, name: string}
     *
     * @throws ParsingException
     */
    public static function parseSymfonyDatabaseUrl(string $dbCredentials): array
    {
        $value = str_replace("\\n'", '', $dbCredentials);

        if (str_starts_with($value, 'DATABASE_URL=')) {
            $value = substr($value, strlen('DATABASE_URL='));
        }

        $unquoted = Pure::removeSurroundingQuotes($value);
        $url = is_string($unquoted) ? $unquoted : '';

        $parsed = parse_url($url);
        if (false === $parsed) {
            throw new ParsingException('Mismatch of expected database credentials');
        }

        $scheme = $parsed['scheme'] ?? null;
        $user = $parsed['user'] ?? null;
        $host = $parsed['host'] ?? null;
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? null;

        if (self::isBlank($scheme) || self::isBlank($user) || self::isBlank($host) || null === $port || self::isBlank($path)) {
            throw new ParsingException('Mismatch of expected database credentials');
        }

        if (!array_key_exists('pass', $parsed)) {
            throw new ParsingException('Mismatch of expected database credentials');
        }

        $name = ltrim($path, '/');
        if ('' === $name) {
            throw new ParsingException('Mismatch of expected database credentials');
        }

        return [
            'db_type' => $scheme,
            'user' => rawurldecode($user),
            'password' => rawurldecode($parsed['pass']),
            'host' => $host,
            'port' => (string) $port,
            'name' => $name,
        ];
    }

    /**
     * @param array<string, mixed> $dbCredentials
     *
     * @return array{name: mixed, host: mixed, password: mixed, port: mixed, user: mixed}
     *
     * @throws OutOfBoundsException when a required drush key is missing (mirrors Python KeyError)
     */
    public static function parseDrupalDrushCredentials(array $dbCredentials): array
    {
        return [
            'name' => self::requireKey($dbCredentials, 'db-name'),
            'host' => self::requireKey($dbCredentials, 'db-hostname'),
            'password' => self::requireKey($dbCredentials, 'db-password'),
            'port' => self::requireKey($dbCredentials, 'db-port'),
            'user' => self::requireKey($dbCredentials, 'db-username'),
        ];
    }

    /**
     * Handles TYPO3 v8+ (nested Connections.Default) and v7- (flat database/username),
     * preserving every extra field and defaulting the port to the integer 3306.
     *
     * @param array<string, mixed> $dbCredentials
     *
     * @return array<string, mixed>
     *
     * @throws OutOfBoundsException when a required key is missing (mirrors Python KeyError)
     */
    public static function parseTypo3DatabaseCredentials(array $dbCredentials): array
    {
        if (array_key_exists('Connections', $dbCredentials)) {
            $connections = $dbCredentials['Connections'];
            if (!is_array($connections) || !array_key_exists('Default', $connections) || !is_array($connections['Default'])) {
                throw new OutOfBoundsException('Connections.Default');
            }

            $config = $connections['Default'];
            $config['name'] = self::requireKey($config, 'dbname');
        } else {
            $config = $dbCredentials;
            $config['user'] = self::requireKey($config, 'username');
            $config['name'] = self::requireKey($config, 'database');
        }

        if (!array_key_exists('port', $config)) {
            $config['port'] = 3306;
        }

        return $config;
    }

    /**
     * @phpstan-assert-if-false non-empty-string $value
     */
    private static function isBlank(?string $value): bool
    {
        return null === $value || '' === $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireKey(array $data, string $key): mixed
    {
        if (!array_key_exists($key, $data)) {
            throw new OutOfBoundsException(sprintf('Missing key: %s', $key));
        }

        return $data[$key];
    }
}
