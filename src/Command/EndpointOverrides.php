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

namespace MoveElevator\DbSyncTool\Command;

final class EndpointOverrides
{
    /**
     * CLI suffix (after `origin-`/`target-`) => top-level client config key.
     *
     * @var array<string, string>
     */
    public const SUFFIX_MAP = [
        'path' => 'path',
        'name' => 'name',
        'host' => 'host',
        'user' => 'user',
        'password' => 'password',
        'key' => 'ssh_key',
        'port' => 'port',
        'dump-dir' => 'dump_dir',
        'keep-dumps' => 'keep_dumps',
    ];

    /**
     * CLI suffix => key inside the nested `db` block.
     *
     * @var array<string, string>
     */
    public const DB_SUFFIX_MAP = [
        'db-name' => 'name',
        'db-host' => 'host',
        'db-user' => 'user',
        'db-password' => 'password',
        'db-port' => 'port',
    ];

    /**
     * @param array<string, string|null> $raw suffix => raw CLI value (null when unset)
     *
     * @return array<string, mixed>
     */
    public static function build(array $raw): array
    {
        $result = [];

        foreach (self::SUFFIX_MAP as $suffix => $key) {
            if (null !== ($raw[$suffix] ?? null)) {
                $result[$key] = $raw[$suffix];
            }
        }

        $db = [];
        foreach (self::DB_SUFFIX_MAP as $suffix => $key) {
            if (null !== ($raw[$suffix] ?? null)) {
                $db[$key] = $raw[$suffix];
            }
        }
        if ([] !== $db) {
            $result['db'] = $db;
        }

        return $result;
    }
}
