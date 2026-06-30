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

namespace MoveElevator\DbSyncTool\Recipe;

use MoveElevator\DbSyncTool\Util\Pure;

use function is_string;

/**
 * Extractors.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Extractors
{
    /**
     * TYPO3 .env: TYPO3_CONF_VARS__DB__Connections__Default__* variables.
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function typo3FromEnv(string $content): array
    {
        $prefix = 'TYPO3_CONF_VARS__DB__Connections__Default__';

        return [
            'name' => self::envValue($content, $prefix.'dbname'),
            'host' => self::envValue($content, $prefix.'host'),
            'user' => self::envValue($content, $prefix.'user'),
            'password' => self::envValue($content, $prefix.'password'),
            'port' => self::withPortDefault(self::envValue($content, $prefix.'port')),
        ];
    }

    /**
     * TYPO3 AdditionalConfiguration.php / additional.php: 'key' => 'value'.
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function typo3FromAdditional(string $content): array
    {
        return [
            'name' => self::phpArrowValue($content, 'dbname'),
            'host' => self::phpArrowValue($content, 'host'),
            'user' => self::phpArrowValue($content, 'user'),
            'password' => self::phpArrowValue($content, 'password'),
            'port' => self::withPortDefault(self::phpArrowValue($content, 'port')),
        ];
    }

    /**
     * Drupal settings.php: $databases['default']['default']['key'] => value.
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function drupalFromSettings(string $content): array
    {
        return [
            'name' => self::drupalValue($content, 'database'),
            'host' => self::drupalValue($content, 'host'),
            'user' => self::drupalValue($content, 'username'),
            'password' => self::drupalValue($content, 'password'),
            'port' => self::withPortDefault(self::drupalValue($content, 'port')),
        ];
    }

    /**
     * WordPress wp-config.php: define( 'DB_*', 'value' ).
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function wordpressFromConfig(string $content): array
    {
        return [
            'name' => self::wpDefine($content, 'DB_NAME'),
            'host' => self::wpDefine($content, 'DB_HOST'),
            'user' => self::wpDefine($content, 'DB_USER'),
            'password' => self::wpDefine($content, 'DB_PASSWORD'),
            'port' => self::withPortDefault(self::wpDefine($content, 'DB_PORT')),
        ];
    }

    /**
     * Laravel .env: DB_* variables (no port default, mirroring the recipe).
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function laravelFromEnv(string $content): array
    {
        return [
            'name' => self::laravelValue($content, 'DB_DATABASE'),
            'host' => self::laravelValue($content, 'DB_HOST'),
            'user' => self::laravelValue($content, 'DB_USERNAME'),
            'password' => self::laravelValue($content, 'DB_PASSWORD'),
            'port' => self::laravelValue($content, 'DB_PORT'),
        ];
    }

    /**
     * Symfony .env (>=3.4): the first non-comment DATABASE_URL line.
     */
    public static function symfonyDatabaseUrlLine(string $content): string
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (str_contains($line, 'DATABASE_URL')) {
                return trim($line);
            }
        }

        return '';
    }

    /**
     * Symfony parameters.yml (<=2.8): database_* parameters.
     *
     * @return array{name: string, host: string, user: string, password: string, port: string}
     */
    public static function symfonyFromParameters(string $content): array
    {
        return [
            'name' => self::yamlValue($content, 'database_name'),
            'host' => self::yamlValue($content, 'database_host'),
            'user' => self::yamlValue($content, 'database_user'),
            'password' => self::yamlValue($content, 'database_password'),
            'port' => self::yamlValue($content, 'database_port'),
        ];
    }

    private static function envValue(string $content, string $name): string
    {
        return self::clean(self::firstMatch('/^'.preg_quote($name, '/').'=(.*)$/m', $content));
    }

    private static function phpArrowValue(string $content, string $key): string
    {
        return self::clean(self::firstMatch("/'".preg_quote($key, '/')."'.*=>.*'(.*)'.*$/m", $content));
    }

    private static function drupalValue(string $content, string $key): string
    {
        $quoted = self::firstMatch(
            "/.*'".preg_quote($key, '/')."' *=> *['\"]([^'\"]*)['\"].*/m",
            $content,
        );
        if ('' !== $quoted) {
            return self::clean($quoted);
        }

        return self::clean(self::firstMatch("/.*'".preg_quote($key, '/')."' *=> *([0-9]+).*/m", $content));
    }

    private static function wpDefine(string $content, string $key): string
    {
        return self::clean(self::firstMatch(
            "/define\\( *'".preg_quote($key, '/')."', *'([^']*)'.*/m",
            $content,
        ));
    }

    private static function laravelValue(string $content, string $key): string
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (str_contains($line, $key)) {
                $parts = explode('=', $line, 2);

                return self::clean($parts[1] ?? '');
            }
        }

        return '';
    }

    private static function yamlValue(string $content, string $key): string
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (str_contains($line, $key)) {
                return self::clean((string) preg_replace('/.*:\s*/', '', $line));
            }
        }

        return '';
    }

    private static function firstMatch(string $pattern, string $content): string
    {
        if (1 === preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function withPortDefault(string $port): string
    {
        return '' === $port ? '3306' : $port;
    }

    private static function clean(string $value): string
    {
        $unquoted = Pure::removeSurroundingQuotes(trim($value));

        return is_string($unquoted) ? $unquoted : '';
    }
}
