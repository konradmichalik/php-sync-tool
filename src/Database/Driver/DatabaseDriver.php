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

use KonradMichalik\SyncTool\Config\{AnonymizationRule, DatabaseConfig, SyncConfig};
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
     * One statement matching every given pattern at once. A configuration listing
     * ten `cache_*` style patterns cost ten round trips to the endpoint when each
     * one was asked separately.
     *
     * @param non-empty-list<string> $patterns SQL LIKE patterns, `%` already substituted for `*`
     */
    public function listTablesMatchingSql(string $dbName, array $patterns): string;

    /**
     * Table names from the raw client output of listTablesSql()/listTablesMatchingSql().
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
     * One UPDATE statement per masking rule, in the dialect of this system.
     *
     * @param list<AnonymizationRule> $rules
     *
     * @return list<string>
     */
    public function anonymizeStatements(array $rules): array;

    /**
     * Configured features this driver cannot express, named as their config keys.
     * Sync refuses to run instead of silently ignoring them.
     *
     * @param DatabaseConfig $db the endpoint being acted on, since some keys are
     *                           configured per endpoint rather than per run
     *
     * @return list<string>
     */
    public function unsupportedFeatures(SyncConfig $config, DatabaseConfig $db): array;
}
