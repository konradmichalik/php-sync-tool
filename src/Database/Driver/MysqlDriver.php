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
use KonradMichalik\SyncTool\Database\{ClientBinaries, DumpRequest, MysqlCommandBuilder, MysqlDefaultsFile, TableStatements};
use KonradMichalik\SyncTool\Enum\{AnonymizationStrategy, DatabaseSystem};
use KonradMichalik\SyncTool\Security\{SqlLiteral, TableName};

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function explode;
use function implode;
use function sprintf;
use function trim;

/**
 * MysqlDriver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class MysqlDriver implements DatabaseDriver
{
    public function __construct(
        private MysqlCommandBuilder $commands = new MysqlCommandBuilder(),
        private MysqlDefaultsFile $defaultsFile = new MysqlDefaultsFile(),
        private TableStatements $tables = new TableStatements(),
        private ClientBinaries $binaries = new ClientBinaries(),
    ) {}

    public function system(): DatabaseSystem
    {
        return DatabaseSystem::MySQL;
    }

    public function credentialsContent(DatabaseConfig $db): string
    {
        return $this->defaultsFile->buildContent($db);
    }

    public function credentialsPath(): string
    {
        return $this->defaultsFile->generatePath();
    }

    public function dumpCommand(DumpRequest $request): string
    {
        $ignoreOptions = implode(' ', array_map(
            fn (string $table): string => $this->tables->ignoreTableOption($request->db->name, $table),
            $request->ignoreTables,
        ));

        return $this->commands->dumpCommand(
            $this->binaries->dump,
            $this->argument($request->credentialsPath),
            $this->commands->dumpOptions($request->where, $request->additionalOptions),
            $request->db->name,
            $ignoreOptions,
            $request->exportTables,
            'gzip',
            $request->dumpFilePath,
        );
    }

    public function importCommand(DatabaseConfig $db, string $credentialsPath, string $filepath): string
    {
        return $this->commands->importCommand($this->binaries->client, $this->argument($credentialsPath), $db->name, 'gunzip', $filepath);
    }

    public function execCommand(DatabaseConfig $db, string $credentialsPath, string $sql): string
    {
        return $this->commands->execCommand($this->binaries->client, $this->argument($credentialsPath), $db->name, $sql);
    }

    /**
     * `mysql` already runs with the database selected.
     */
    public function listTablesSql(): string
    {
        return 'SHOW TABLES;';
    }

    public function listTablesLikeSql(string $dbName, string $pattern): string
    {
        return $this->commands->showTablesLikeSql($dbName, $pattern);
    }

    public function parseTableList(string $output): array
    {
        // `mysql -e` prints a header row for every result set.
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $output))));

        return array_slice($lines, 1);
    }

    public function dropTablesStatement(array $tables): ?string
    {
        return $this->tables->foreignKeyDisabledBatch(array_map($this->tables->dropStatement(...), $tables));
    }

    public function truncateTablesStatement(array $tables): ?string
    {
        return $this->tables->foreignKeyDisabledBatch(array_map($this->tables->truncateStatement(...), $tables));
    }

    public function anonymizeStatements(array $rules): array
    {
        $statements = [];

        foreach ($rules as $rule) {
            $table = TableName::sanitize($rule->table);
            $column = TableName::sanitize($rule->column);

            $statements[] = sprintf('UPDATE %s SET %s = %s;', $table, $column, match ($rule->strategy) {
                AnonymizationStrategy::Nullify => 'NULL',
                AnonymizationStrategy::StaticValue => SqlLiteral::quote((string) $rule->value),
                AnonymizationStrategy::Hash => sprintf('MD5(%s)', $column),
                AnonymizationStrategy::Email => sprintf('CONCAT(MD5(%s), %s)', $column, SqlLiteral::quote(AnonymizationStrategy::MASKED_MAIL_DOMAIN)),
            });
        }

        return $statements;
    }

    public function unsupportedFeatures(SyncConfig $config, DatabaseConfig $db): array
    {
        return [];
    }

    private function argument(string $credentialsPath): string
    {
        return '--defaults-extra-file='.$credentialsPath;
    }
}
