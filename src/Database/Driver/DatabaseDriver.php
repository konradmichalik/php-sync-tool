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

namespace KonradMichalik\SyncTool\Database\Driver;

use KonradMichalik\SyncTool\Config\{DatabaseConfig, SyncConfig};
use KonradMichalik\SyncTool\Database\DumpRequest;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;

/**
 * DatabaseDriver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface DatabaseDriver
{
    public function system(): DatabaseSystem;

    /**
     * Content of the credential file that keeps the password off the command line.
     */
    public function credentialsContent(DatabaseConfig $db): string;

    /**
     * Absolute path for a fresh credential file. Randomized, never reused.
     */
    public function credentialsPath(): string;

    public function dumpCommand(DumpRequest $request): string;

    public function importCommand(DatabaseConfig $db, string $credentialsPath, string $filepath): string;

    public function execCommand(DatabaseConfig $db, string $credentialsPath, string $sql): string;

    public function listTablesSql(): string;

    /**
     * @param string $pattern SQL LIKE pattern, `%` already substituted for `*`
     */
    public function listTablesLikeSql(string $dbName, string $pattern): string;

    /**
     * Table names from the raw client output of listTablesSql()/listTablesLikeSql().
     *
     * @return list<string>
     */
    public function parseTableList(string $output): array;

    /**
     * One statement that drops all given tables, or null when there is nothing to drop.
     *
     * @param list<string> $tables
     */
    public function dropTablesStatement(array $tables): ?string;

    /**
     * @param list<string> $tables
     */
    public function truncateTablesStatement(array $tables): ?string;

    /**
     * Configured features this driver cannot express, named as their config keys.
     * Sync refuses to run instead of silently ignoring them.
     *
     * @return list<string>
     */
    public function unsupportedFeatures(SyncConfig $config): array;
}
