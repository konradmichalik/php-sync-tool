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

namespace KonradMichalik\SyncTool;

use Closure;
use KonradMichalik\SyncTool\Backup\{DumpFileNamer, DumpManager};
use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Database\Driver\{DatabaseDriver, DriverFactory};
use KonradMichalik\SyncTool\Database\{DumpRequest, RemoteFileWriter, TableStatements};
use KonradMichalik\SyncTool\Enum\{LifecyclePhase, LogChannel};
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Lifecycle\ScriptRunner;
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Recipe\CredentialResolver;
use KonradMichalik\SyncTool\Remote\{CommandRunner, FileSync, RunnerFactory};
use KonradMichalik\SyncTool\Remote\Transfer\{TransferPayload, TransferStrategyResolver};
use KonradMichalik\SyncTool\Security\LogSanitizer;
use Throwable;

use function implode;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * Sync.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class Sync
{
    /** @var Closure(string, LogChannel=): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private DriverFactory $drivers = new DriverFactory(),
        private RemoteFileWriter $remoteFileWriter = new RemoteFileWriter(),
        private TableStatements $tables = new TableStatements(),
        private TransferStrategyResolver $transferResolver = new TransferStrategyResolver(),
        private DumpFileNamer $namer = new DumpFileNamer(),
        private FileSync $fileSync = new FileSync(),
        private ScriptRunner $scripts = new ScriptRunner(),
        private DumpManager $dumps = new DumpManager(),
        private CredentialResolver $credentialResolver = new CredentialResolver(),
        ?Closure $log = null,
        private SyncProgress $progress = new NullSyncProgress(),
    ) {
        $this->log = $log ?? static function (string $message, LogChannel $channel = LogChannel::Step): void {};
    }

    public function run(SyncConfig $config, SyncPlan $plan): void
    {
        $config = $this->resolveCredentials($config);

        $local = $this->runners->local();
        $this->scripts->run($local, $config, LifecyclePhase::Before);

        try {
            if (!$config->filesOnly) {
                $dumpName = $this->namer->generate($config);

                if (!$plan->isImport()) {
                    $this->createOriginDump($config, $dumpName);
                }

                if (!$plan->isDump()) {
                    $this->transferDump($config, $plan, $dumpName);
                }

                if (!$config->keepDump && !$plan->isDump()) {
                    $this->importDump($config, $plan, $dumpName);
                }
            }

            if ($config->filesOnly || $config->withFiles) {
                ($this->log)('Synchronizing files');
                $this->fileSync->sync($config, $plan, $this->log, $this->progress);
            }

            $this->scripts->run($local, $config, LifecyclePhase::After);
        } catch (Throwable $exception) {
            $this->scripts->run($local, $config, LifecyclePhase::Error);

            throw $exception;
        }
    }

    private function resolveCredentials(SyncConfig $config): SyncConfig
    {
        $origin = $config->origin;
        $target = $config->target;

        if ('' === $origin->db->name && '' !== $origin->path) {
            $runner = $this->runners->forClient($origin, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
            $db = $this->credentialResolver->resolve($config, $origin, $runner);
            if (null !== $db) {
                $origin = $origin->withDb($db);
            }
        }

        if ('' === $target->db->name && '' !== $target->path) {
            $runner = $this->runners->forClient($target, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
            $db = $this->credentialResolver->resolve($config, $target, $runner);
            if (null !== $db) {
                $target = $target->withDb($db);
            }
        }

        return $config->withClients($origin, $target);
    }

    private function createOriginDump(SyncConfig $config, string $dumpName): void
    {
        $client = $config->origin;
        $runner = $this->runners->forClient($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
        $driver = $this->drivers->forDatabase($client->db);
        $this->assertSupported($driver, $config);

        $credentialsPath = $this->prepareCredentials($client, $runner, $driver);

        try {
            $command = $driver->dumpCommand(new DumpRequest(
                db: $client->db,
                credentialsPath: $credentialsPath,
                dumpFilePath: $this->dumpDir($client).$dumpName,
                exportTables: $this->tables->exportTables($config->tables),
                ignoreTables: $this->resolveIgnoreTables($config, $runner, $driver, $credentialsPath),
                where: $config->where,
                additionalOptions: $config->additionalMysqldumpOptions,
            ));

            ($this->log)('Creating origin dump '.$dumpName);
            $this->logCommand($command);
            $this->progress->phase('Creating origin dump');
            $runner->run($command);
            $this->progress->advance();

            $this->pruneDumps($client, $runner);
        } finally {
            $this->cleanupCredentials($client, $runner, $credentialsPath);
        }
    }

    private function transferDump(SyncConfig $config, SyncPlan $plan, string $dumpName): void
    {
        if ($plan->isImport()) {
            return;
        }

        $payload = new TransferPayload(
            $this->dumpDir($config->origin).$dumpName.'.gz',
            $this->dumpDir($config->target).$dumpName.'.gz',
            extraRsyncOptions: $config->useRsyncOptions,
        );

        $strategy = $this->transferResolver->resolve($config, $plan, $this->log, $this->progress);
        ($this->log)('Transferring dump'.$strategy->describe());
        $this->progress->phase($payload->label());
        $strategy->transfer($config, $payload);
        $this->progress->advance();
    }

    private function importDump(SyncConfig $config, SyncPlan $plan, string $dumpName): void
    {
        $client = $config->target;
        $runner = $this->runners->forClient($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
        $driver = $this->drivers->forDatabase($client->db);
        $this->assertSupported($driver, $config);

        $credentialsPath = $this->prepareCredentials($client, $runner, $driver);

        try {
            $dumpPath = $plan->isImport() && '' !== $config->importFile
                ? $config->importFile
                : $this->dumpDir($client).$dumpName.'.gz';

            if ($config->checkDump) {
                $this->checkDump($runner, $dumpPath);
            }

            if ($config->clearDatabase) {
                $this->clearDatabase($config, $runner, $driver, $credentialsPath);
            }

            $this->truncateTables($config, $runner, $driver, $credentialsPath);

            $command = $driver->importCommand($client->db, $credentialsPath, $dumpPath);

            ($this->log)('Importing dump into target');
            $this->logCommand($command);
            $this->progress->phase('Importing dump');
            $runner->run($command);
            $this->progress->advance();

            $this->importAfterDump($client, $runner, $driver, $credentialsPath);
            $this->anonymizeData($client, $runner, $driver, $credentialsPath);
            $this->runPostSql($client, $runner, $driver, $credentialsPath);
            $this->pruneDumps($client, $runner);
        } finally {
            $this->cleanupCredentials($client, $runner, $credentialsPath);
        }
    }

    private function checkDump(CommandRunner $runner, string $dumpPath): void
    {
        $result = $runner->run(sprintf('test -s %s && echo VALID', escapeshellarg($dumpPath)), true);

        if ('VALID' !== trim($result)) {
            throw new SyncException(sprintf('Dump validation failed: %s is missing or empty', $dumpPath));
        }
    }

    private function importAfterDump(ClientConfig $client, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        if (null === $client->afterDump || '' === $client->afterDump) {
            return;
        }

        ($this->log)('Importing additional dump '.$client->afterDump);
        $this->progress->phase('Importing additional dump');
        $runner->run($driver->importCommand($client->db, $credentialsPath, $client->afterDump));
        $this->progress->advance();
    }

    /**
     * Masks the imported copy. Runs after every import step, so rows brought in by
     * `after_dump` are covered too, and before `post_sql`, so custom SQL sees
     * already-masked data. A failing statement aborts the sync rather than leaving
     * a copy with plaintext data behind.
     */
    private function anonymizeData(ClientConfig $client, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        $statements = $driver->anonymizeStatements($client->anonymize);

        if ([] === $statements) {
            return;
        }

        ($this->log)('Anonymizing data');
        $this->progress->phase('Anonymizing data');

        foreach ($statements as $statement) {
            $command = $driver->execCommand($client->db, $credentialsPath, $statement);
            $this->logCommand($command);
            $runner->run($command);
        }

        $this->progress->advance();
    }

    private function runPostSql(ClientConfig $client, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        foreach ($client->postSql as $sql) {
            if ('' === $sql) {
                continue;
            }

            ($this->log)('Running post-import SQL');
            $this->progress->phase('Running post-import SQL');
            $runner->run($driver->execCommand($client->db, $credentialsPath, $sql));
            $this->progress->advance();
        }
    }

    private function pruneDumps(ClientConfig $client, CommandRunner $runner): void
    {
        if (null === $client->keepDumps) {
            return;
        }

        $isDarwin = !$client->isRemote() && 'Darwin' === \PHP_OS_FAMILY;
        $listing = $runner->run(
            $this->dumps->listDumpsCommand('stat', 'sort', 'grep', $this->dumpDir($client).'*', $isDarwin),
            true,
        );

        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $listing))));
        $files = array_map($this->dumps->extractFilename(...), $lines);

        foreach ($this->dumps->filesToRemove($files, $client->keepDumps) as $file) {
            ($this->log)('Removing old dump '.$file);
            $runner->run(sprintf('rm -f %s', escapeshellarg($file)), true);
        }
    }

    private function clearDatabase(SyncConfig $config, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        $db = $config->target->db;
        $output = $runner->run($driver->execCommand($db, $credentialsPath, $driver->listTablesSql()));
        $statement = $driver->dropTablesStatement($driver->parseTableList($output));

        if (null !== $statement) {
            ($this->log)('Clearing target database');
            $runner->run($driver->execCommand($db, $credentialsPath, $statement));
        }
    }

    private function truncateTables(SyncConfig $config, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        $statement = $driver->truncateTablesStatement($config->truncateTables);

        if (null !== $statement) {
            ($this->log)('Truncating tables');
            $runner->run($driver->execCommand($config->target->db, $credentialsPath, $statement));
        }
    }

    /**
     * Table names to skip, with every `table*` wildcard expanded through a live
     * query on the origin. Rendering them as command options is the driver's job.
     *
     * @return list<string>
     */
    private function resolveIgnoreTables(SyncConfig $config, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): array
    {
        $db = $config->origin->db;
        $tables = [];

        foreach ($config->ignoreTables as $table) {
            if (!str_contains($table, '*')) {
                $tables[] = $table;

                continue;
            }

            $sql = $driver->listTablesLikeSql($db->name, str_replace('*', '%', $table));
            $output = $runner->run($driver->execCommand($db, $credentialsPath, $sql), true);

            foreach ($driver->parseTableList($output) as $match) {
                $tables[] = $match;
            }
        }

        return $tables;
    }

    /**
     * Refuse a run that asks for something this database system cannot express,
     * before anything is dumped, transferred or imported.
     */
    private function assertSupported(DatabaseDriver $driver, SyncConfig $config): void
    {
        $unsupported = $driver->unsupportedFeatures($config);

        if ([] !== $unsupported) {
            throw new SyncException(sprintf('%s does not support: %s', $driver->system()->value, implode(', ', $unsupported)));
        }
    }

    private function prepareCredentials(ClientConfig $client, CommandRunner $runner, DatabaseDriver $driver): string
    {
        $content = $driver->credentialsContent($client->db);
        $path = $driver->credentialsPath();

        if ($client->isRemote()) {
            $runner->run($this->remoteFileWriter->remoteWriteCommand($content, $path));
        } else {
            file_put_contents($path, $content);
            chmod($path, 0o600);
        }

        return $path;
    }

    private function cleanupCredentials(ClientConfig $client, CommandRunner $runner, string $path): void
    {
        if ($client->isRemote()) {
            $runner->run(sprintf('rm -f %s', escapeshellarg($path)), true);

            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function dumpDir(ClientConfig $client): string
    {
        return rtrim($client->dumpDir, '/').'/';
    }

    private function logCommand(string $command): void
    {
        ($this->log)('  $ '.LogSanitizer::sanitize($command), LogChannel::Command);
    }
}
