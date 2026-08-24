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

namespace KonradMichalik\SyncTool\Enum;

use function strtolower;
use function trim;

/**
 * DatabaseSystem.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum DatabaseSystem: string
{
    case MySQL = 'MySQL';
    case MariaDB = 'MariaDB';
    case PostgreSQL = 'PostgreSQL';

    /**
     * The system a framework driver, a `db.type` or a URL scheme names, falling
     * back to MySQL: that is what an endpoint saying nothing has always been.
     */
    public static function fromDriver(string $value): self
    {
        return self::fromConfigValue($value) ?? self::MySQL;
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::MySQL, self::MariaDB => 3306,
            self::PostgreSQL => 5432,
        };
    }

    /**
     * Accepts what users write in `db.type` and what a database URL carries as
     * its scheme, both in any casing.
     */
    public static function fromConfigValue(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'mysql', 'pdo_mysql' => self::MySQL,
            'mariadb' => self::MariaDB,
            'postgres', 'postgresql', 'pgsql', 'pdo_pgsql' => self::PostgreSQL,
            default => null,
        };
    }
}
