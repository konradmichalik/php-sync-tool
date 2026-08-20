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

namespace KonradMichalik\SyncTool\Database;

use KonradMichalik\SyncTool\Config\DatabaseConfig;

use function bin2hex;
use function implode;
use function random_bytes;
use function sprintf;
use function str_replace;

/**
 * PgpassFile.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class PgpassFile
{
    /**
     * PostgreSQL's own defaults, shared with PostgresDriver so that the `.pgpass`
     * line and the connection arguments can never disagree.
     */
    public const DEFAULT_HOST = 'localhost';
    public const DEFAULT_PORT = 5432;

    /**
     * A `.pgpass` line is `host:port:database:user:password`; literal colons and
     * backslashes inside a field are backslash-escaped.
     */
    public function buildContent(DatabaseConfig $db): string
    {
        return implode(':', [
            $this->escape('' !== $db->host ? $db->host : self::DEFAULT_HOST),
            (string) (0 !== $db->port ? $db->port : self::DEFAULT_PORT),
            $this->escape($db->name),
            $this->escape($db->user),
            $this->escape($db->password),
        ])."\n";
    }

    public function generatePath(): string
    {
        return sprintf('/tmp/.pgpass_%s', bin2hex(random_bytes(8)));
    }

    private function escape(string $field): string
    {
        return str_replace(['\\', ':'], ['\\\\', '\\:'], $field);
    }
}
