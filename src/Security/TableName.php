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

namespace MoveElevator\DbSyncTool\Security;

use MoveElevator\DbSyncTool\Exception\ValidationException;

use function sprintf;


/**
 * TableName.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class TableName
{
    /**
     * Validate a table name against an allowlist to prevent SQL injection,
     * returning it backtick-quoted. Mirrors Python's sanitize_table_name():
     * only [a-zA-Z0-9_$.-] are permitted.
     *
     * @throws ValidationException if the name is empty or contains invalid characters
     */
    public static function sanitize(string $table): string
    {
        if ('' === $table) {
            throw new ValidationException('Table name cannot be empty');
        }

        if (1 !== preg_match('/^[a-zA-Z0-9_$.-]+$/', $table)) {
            throw new ValidationException(sprintf('Invalid table name: %s', $table));
        }

        return '`'.$table.'`';
    }
}
