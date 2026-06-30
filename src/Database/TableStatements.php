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

use KonradMichalik\SyncTool\Security\TableName;

use function sprintf;

/**
 * TableStatements.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class TableStatements
{
    /**
     * Validated export table names (backticks stripped, ready for shell-quoting).
     *
     * @return list<string>
     */
    public function exportTables(string $tablesCsv): array
    {
        if ('' === $tablesCsv) {
            return [];
        }

        $tables = [];
        foreach (explode(',', $tablesCsv) as $table) {
            $trimmed = trim($table);
            if ('' === $trimmed) {
                continue;
            }
            $tables[] = trim(TableName::sanitize($trimmed), '`');
        }

        return $tables;
    }

    public function ignoreTableOption(string $dbName, string $table): string
    {
        $safeTable = trim(TableName::sanitize($table), '`');
        $safeDb = trim(TableName::sanitize($dbName), '`');

        return sprintf('--ignore-table=%s.%s', $safeDb, $safeTable);
    }

    public function truncateStatement(string $table): string
    {
        return 'TRUNCATE TABLE '.TableName::sanitize($table);
    }

    public function dropStatement(string $table): string
    {
        return 'DROP TABLE '.TableName::sanitize($table);
    }

    /**
     * Wrap statements in a single FK-disabled batch (one roundtrip), or null
     * when there is nothing to run.
     *
     * @param list<string> $statements
     */
    public function foreignKeyDisabledBatch(array $statements): ?string
    {
        if ([] === $statements) {
            return null;
        }

        return 'SET FOREIGN_KEY_CHECKS = 0; '.implode('; ', $statements).'; SET FOREIGN_KEY_CHECKS = 1;';
    }
}
