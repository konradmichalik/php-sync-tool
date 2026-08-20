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
use KonradMichalik\SyncTool\Database\{CredentialsFile, MysqlCommandBuilder, MysqlCredentials, MysqlDefaultsFile, TableStatements};
use KonradMichalik\SyncTool\Enum\{LifecyclePhase, LogChannel, SyncMode};
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Lifecycle\ScriptRunner;
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Recipe\CredentialResolver;
use KonradMichalik\SyncTool\Remote\{CommandRunner, FileSync, RunnerFactory};
use KonradMichalik\SyncTool\Remote\Transfer\{TransferPayload, TransferStrategyResolver};
use KonradMichalik\SyncTool\Security\LogSanitizer;
use Throwable;

use function array_slice;
use function sprintf;

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
        private MysqlCommandBuilder $commands = new MysqlCommandBuilder(),
        private MysqlCredentials $credentials = new MysqlCredentials(),
        private MysqlDefaultsFile $defaultsFile = new MysqlDefaultsFile(),
        private CredentialsFile $credentialsFile = new CredentialsFile(),
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

    public function run(SyncConfig $config, SyncMode $mode): void
    {
        $config = $this->resolveCredentials($config);

        $local = $this->runners->local();
        $this->scripts->run($local, $config, LifecyclePhase::Before);

        try {
            if (!$config->filesOnly) {
                $dumpName = $this->namer->generate($config);

                if (!$mode->isImport()) {
                    $this->createOriginDump($config, $dumpName);
                }

                if (!$mode->isDump()) {
                    $this->transferDump($config, $mode, $dumpName);
                }

                if (!$config->keepDump && !$mode->isDump()) {
                    $this->importDump($config, $mode, $dumpName);
                }
            }

            if ($config->filesOnly || $config->withFiles) {
                ($this->log)('Synchronizing files');
                $this->fileSync->sync($config, $mode, $this->log, $this->progress);
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

        $credentialsPath = $this->prepareCredentials($client, $runner);
        $credentialsArg = $this->credentials->defaultsExtraFileArgument($credentialsPath);

        try {
            $dumpPath = $this->dumpDir($client).$dumpName;

            $ignoreOptions = $this->ignoreOptions($config, $runner, $credentialsArg);
            $exportTables = $this->tables->exportTables($config->tables);
            $options = $this->commands->dumpOptions(null, null, $config->where, $config->additionalMysqldumpOptions);

            $command = $this->commands->dumpCommand(
                'mysqldump',
                $credentialsArg,
                $options,
                $client->db->name,
                $ignoreOptions,
                $exportTables,
                'gzip',
                $dumpPath,
            );

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

    private function transferDump(SyncConfig $config, SyncMode $mode, string $dumpName): void
    {
        if ($mode->isImport()) {
            return;
        }

        $payload = new TransferPayload(
            $this->dumpDir($config->origin).$dumpName.'.gz',
            $this->dumpDir($config->target).$dumpName.'.gz',
            extraRsyncOptions: $config->useRsyncOptions,
        );

        $strategy = $this->transferResolver->resolve($config, $mode, $this->log, $this->progress);
        ($this->log)('Transferring dump'.$strategy->describe());
        $this->progress->phase($payload->label());
        $strategy->transfer($config, $payload);
        $this->progress->advance();
    }

    private function importDump(SyncConfig $config, SyncMode $mode, string $dumpName): void
    {
        $client = $config->target;
        $runner = $this->runners->forClient($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);

        $credentialsPath = $this->prepareCredentials($client, $runner);
        $credentialsArg = $this->credentials->defaultsExtraFileArgument($credentialsPath);

        try {
            $dumpPath = $mode->isImport() && '' !== $config->importFile
                ? $config->importFile
                : $this->dumpDir($client).$dumpName.'.gz';

            if ($config->checkDump) {
                $this->checkDump($runner, $dumpPath);
            }

            if ($config->clearDatabase) {
                $this->clearDatabase($config, $runner, $credentialsArg);
            }

            $this->truncateTables($config, $runner, $credentialsArg);

            $command = $this->commands->importCommand('mysql', $credentialsArg, $client->db->name, 'gunzip', $dumpPath);

            ($this->log)('Importing dump into target');
            $this->logCommand($command);
            $this->progress->phase('Importing dump');
            $runner->run($command);
            $this->progress->advance();

            $this->importAfterDump($client, $runner, $credentialsArg);
            $this->runPostSql($client, $runner, $credentialsArg);
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

    private function importAfterDump(ClientConfig $client, CommandRunner $runner, string $credentialsArg): void
    {
        if (null === $client->afterDump || '' === $client->afterDump) {
            return;
        }

        ($this->log)('Importing additional dump '.$client->afterDump);
        $this->progress->phase('Importing additional dump');
        $runner->run($this->commands->importCommand('mysql', $credentialsArg, $client->db->name, 'gunzip', $client->afterDump));
        $this->progress->advance();
    }

    private function runPostSql(ClientConfig $client, CommandRunner $runner, string $credentialsArg): void
    {
        foreach ($client->postSql as $sql) {
            if ('' === $sql) {
                continue;
            }

            ($this->log)('Running post-import SQL');
            $this->progress->phase('Running post-import SQL');
            $runner->run($this->commands->execCommand('mysql', $credentialsArg, $client->db->name, $sql));
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

    private function clearDatabase(SyncConfig $config, CommandRunner $runner, string $credentialsArg): void
    {
        $result = $runner->run($this->commands->execCommand('mysql', $credentialsArg, $config->target->db->name, 'SHOW TABLES;'));
        $lines = array_slice(array_filter(array_map(trim(...), explode("\n", $result))), 1);
        $statements = array_map($this->tables->dropStatement(...), $lines);

        $batch = $this->tables->foreignKeyDisabledBatch($statements);
        if (null !== $batch) {
            ($this->log)('Clearing target database');
            $runner->run($this->commands->execCommand('mysql', $credentialsArg, $config->target->db->name, $batch));
        }
    }

    private function truncateTables(SyncConfig $config, CommandRunner $runner, string $credentialsArg): void
    {
        if ([] === $config->truncateTables) {
            return;
        }

        $statements = array_map(
            $this->tables->truncateStatement(...),
            $config->truncateTables,
        );

        $batch = $this->tables->foreignKeyDisabledBatch($statements);
        if (null !== $batch) {
            ($this->log)('Truncating tables');
            $runner->run($this->commands->execCommand('mysql', $credentialsArg, $config->target->db->name, $batch));
        }
    }

    private function ignoreOptions(SyncConfig $config, CommandRunner $runner, string $credentialsArg): string
    {
        $dbName = $config->origin->db->name;
        $options = [];

        foreach ($config->ignoreTables as $table) {
            if (str_contains($table, '*')) {
                foreach ($this->expandWildcardTables($dbName, $runner, $credentialsArg, $table) as $match) {
                    $options[] = $this->tables->ignoreTableOption($dbName, $match);
                }

                continue;
            }

            $options[] = $this->tables->ignoreTableOption($dbName, $table);
        }

        return implode(' ', $options);
    }

    /**
     * Expand a `table*` wildcard to the matching table names via a live
     * `SHOW TABLES … LIKE 'table%'` query on the origin.
     *
     * @return list<string>
     */
    private function expandWildcardTables(string $dbName, CommandRunner $runner, string $credentialsArg, string $pattern): array
    {
        $sql = $this->commands->showTablesLikeSql($dbName, str_replace('*', '%', $pattern));
        $result = $runner->run($this->commands->execCommand('mysql', $credentialsArg, $dbName, $sql), true);

        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $result))));

        return array_slice($lines, 1);
    }

    private function prepareCredentials(ClientConfig $client, CommandRunner $runner): string
    {
        $content = $this->defaultsFile->buildContent($client->db);
        $path = $this->defaultsFile->generatePath();

        if ($client->isRemote()) {
            $runner->run($this->credentialsFile->remoteWriteCommand($content, $path));
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
