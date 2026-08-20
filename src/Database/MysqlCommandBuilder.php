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

use KonradMichalik\SyncTool\Security\{Shell, TableName};

use function sprintf;

/**
 * MysqlCommandBuilder.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlCommandBuilder
{
    private const BASE_DUMP_OPTIONS = '--single-transaction --quick --extended-insert --no-tablespaces ';

    /**
     * mysqldump option string. An optional WHERE clause and additional options
     * are appended verbatim.
     */
    public function dumpOptions(string $where = '', string $additional = ''): string
    {
        $options = self::BASE_DUMP_OPTIONS;

        if ('' !== $where) {
            $options .= "--where='".$where."' ";
        }

        if ('' !== $additional) {
            $options .= $additional.' ';
        }

        return $options;
    }

    /**
     * mysqldump … | gzip > dump.sql.gz.
     *
     * @param list<string> $exportTables raw (validated) table names, shell-quoted here
     */
    public function dumpCommand(
        string $mysqldumpBin,
        string $credentialsArg,
        string $options,
        string $dbName,
        string $ignoreOptions,
        array $exportTables,
        string $gzipBin,
        string $dumpFilePath,
    ): string {
        $safeTables = '';
        if ([] !== $exportTables) {
            $safeTables = ' '.implode(' ', array_map(Shell::quote(...), $exportTables));
        }

        return $mysqldumpBin.' '.$credentialsArg.' '
            .$options.Shell::quote($dbName).' '
            .$ignoreOptions.$safeTables
            .' | '.$gzipBin.' > '.Shell::quote($dumpFilePath.'.gz');
    }

    /**
     * gunzip -c dump.gz | mysql … db   OR   mysql … db < dump.sql.
     */
    public function importCommand(
        string $mysqlBin,
        string $credentialsArg,
        string $dbName,
        string $gunzipBin,
        string $filepath,
    ): string {
        $mysqlCommand = $mysqlBin.' '.$credentialsArg.' '.Shell::quote($dbName);
        $safeFilepath = Shell::quote($filepath);

        if (str_ends_with($filepath, '.gz')) {
            return $gunzipBin.' -c '.$safeFilepath.' | '.$mysqlCommand;
        }

        return $mysqlCommand.' < '.$safeFilepath;
    }

    /**
     * mysql … [db] -e "…" with the SQL escaped for a double-quoted shell argument
     * (\\ → \\\\, " → \\", ` → \\`).
     */
    public function execCommand(string $mysqlBin, string $credentialsArg, ?string $dbName, string $sql): string
    {
        $databaseName = null !== $dbName ? ' '.Shell::quote($dbName) : '';
        $safeSql = str_replace(['\\', '"', '`'], ['\\\\', '\\"', '\\`'], $sql);

        return $mysqlBin.' '.$credentialsArg.$databaseName.' -e "'.$safeSql.'"';
    }

    public function showTablesLikeSql(string $dbName, string $pattern): string
    {
        $safePattern = str_replace("'", "''", $pattern);

        return sprintf("SHOW TABLES FROM %s LIKE '%s';", TableName::sanitize($dbName), $safePattern);
    }
}
