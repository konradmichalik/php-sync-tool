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
use KonradMichalik\SyncTool\Database\{DumpRequest, PgpassFile};
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use KonradMichalik\SyncTool\Security\{Shell, TableName};

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function trim;

/**
 * PostgresDriver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class PostgresDriver implements DatabaseDriver
{
    private const DEFAULT_HOST = 'localhost';
    private const DEFAULT_PORT = 5432;

    public function __construct(
        private PgpassFile $pgpass = new PgpassFile(),
    ) {}

    public function system(): DatabaseSystem
    {
        return DatabaseSystem::PostgreSQL;
    }

    public function credentialsContent(DatabaseConfig $db): string
    {
        return $this->pgpass->buildContent($db);
    }

    public function credentialsPath(): string
    {
        return $this->pgpass->generatePath();
    }

    /**
     * `--clean --if-exists` makes the import idempotent, which is what mysqldump's
     * default `DROP TABLE IF EXISTS` prologue provides on the MySQL side.
     */
    public function dumpCommand(DumpRequest $request): string
    {
        $tables = '';
        foreach ($request->exportTables as $table) {
            $tables .= ' -t '.Shell::quote($table);
        }
        foreach ($request->ignoreTables as $table) {
            $tables .= ' -T '.Shell::quote($table);
        }

        return $this->environment($request->credentialsPath)
            .'pg_dump --no-owner --no-privileges --clean --if-exists'
            .$this->connection($request->db)
            .$tables
            .' | gzip > '.Shell::quote($request->dumpFilePath.'.gz');
    }

    public function importCommand(DatabaseConfig $db, string $credentialsPath, string $filepath): string
    {
        $psql = $this->environment($credentialsPath).'psql -v ON_ERROR_STOP=1 -q'.$this->connection($db);
        $safeFilepath = Shell::quote($filepath);

        if (str_ends_with($filepath, '.gz')) {
            return 'gunzip -c '.$safeFilepath.' | '.$psql;
        }

        return $psql.' < '.$safeFilepath;
    }

    public function execCommand(DatabaseConfig $db, string $credentialsPath, string $sql): string
    {
        return $this->environment($credentialsPath)
            .'psql -v ON_ERROR_STOP=1 -t -A'
            .$this->connection($db)
            .' -c '.Shell::quote($sql);
    }

    public function listTablesSql(string $dbName): string
    {
        return "SELECT tablename FROM pg_tables WHERE schemaname = 'public';";
    }

    public function listTablesLikeSql(string $dbName, string $pattern): string
    {
        return sprintf(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE '%s';",
            str_replace("'", "''", $pattern),
        );
    }

    public function parseTableList(string $output): array
    {
        // `psql -t -A` prints one bare value per line, no header.
        return array_values(array_filter(array_map(trim(...), explode("\n", $output))));
    }

    public function dropTablesStatement(array $tables): ?string
    {
        if ([] === $tables) {
            return null;
        }

        return sprintf('DROP TABLE IF EXISTS %s CASCADE;', $this->validatedList($tables));
    }

    public function truncateTablesStatement(array $tables): ?string
    {
        if ([] === $tables) {
            return null;
        }

        return sprintf('TRUNCATE TABLE %s RESTART IDENTITY CASCADE;', $this->validatedList($tables));
    }

    public function unsupportedFeatures(SyncConfig $config): array
    {
        $unsupported = [];

        if ('' !== $config->where) {
            $unsupported[] = 'where';
        }

        if ('' !== $config->additionalMysqldumpOptions) {
            $unsupported[] = 'additional_mysqldump_options';
        }

        return $unsupported;
    }

    /**
     * Identifiers go in unquoted, so PostgreSQL folds them to lowercase.
     *
     * @param list<string> $tables
     */
    private function validatedList(array $tables): string
    {
        return implode(', ', array_map(
            TableName::validate(...),
            $tables,
        ));
    }

    private function environment(string $credentialsPath): string
    {
        return 'PGPASSFILE='.Shell::quote($credentialsPath).' ';
    }

    private function connection(DatabaseConfig $db): string
    {
        return ' -h '.Shell::quote('' !== $db->host ? $db->host : self::DEFAULT_HOST)
            .' -p '.(0 !== $db->port ? $db->port : self::DEFAULT_PORT)
            .' -U '.Shell::quote($db->user)
            .' -d '.Shell::quote($db->name);
    }
}
