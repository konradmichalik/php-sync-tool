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
use KonradMichalik\SyncTool\Database\{MysqlCommandBuilder, MysqlCredentials, MysqlDefaultsFile, TableStatements};
use KonradMichalik\SyncTool\Enum\{LifecyclePhase, SyncMode};
use KonradMichalik\SyncTool\Lifecycle\ScriptRunner;
use KonradMichalik\SyncTool\Remote\{CommandRunner, FileSync, ProxyTransfer, RsyncCommandBuilder, RunnerFactory, SftpTransfer};
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
    /** @var Closure(string): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private MysqlCommandBuilder $commands = new MysqlCommandBuilder(),
        private MysqlCredentials $credentials = new MysqlCredentials(),
        private MysqlDefaultsFile $defaultsFile = new MysqlDefaultsFile(),
        private TableStatements $tables = new TableStatements(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        private DumpFileNamer $namer = new DumpFileNamer(),
        private SftpTransfer $sftp = new SftpTransfer(),
        private ProxyTransfer $proxy = new ProxyTransfer(),
        private FileSync $fileSync = new FileSync(),
        private ScriptRunner $scripts = new ScriptRunner(),
        private DumpManager $dumps = new DumpManager(),
        ?Closure $log = null,
    ) {
        $this->log = $log ?? static function (string $message): void {};
    }

    public function run(SyncConfig $config, SyncMode $mode): void
    {
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
                $this->fileSync->sync($config, $mode);
            }

            $this->scripts->run($local, $config, LifecyclePhase::After);
        } catch (Throwable $exception) {
            $this->scripts->run($local, $config, LifecyclePhase::Error);

            throw $exception;
        }
    }

    private function createOriginDump(SyncConfig $config, string $dumpName): void
    {
        $client = $config->origin;
        $runner = $this->runners->forClient($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);

        $credentialsArg = $this->prepareCredentials($client, $runner);
        $dumpPath = $this->dumpDir($client).$dumpName;

        $ignoreOptions = $this->ignoreOptions($config);
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
        $runner->run($command);

        $this->pruneDumps($client, $runner);
    }

    private function transferDump(SyncConfig $config, SyncMode $mode, string $dumpName): void
    {
        if (SyncMode::ImportLocal === $mode || SyncMode::ImportRemote === $mode) {
            return;
        }

        $originGz = $this->dumpDir($config->origin).$dumpName.'.gz';
        $targetGz = $this->dumpDir($config->target).$dumpName.'.gz';

        if (!$config->origin->isRemote() && !$config->target->isRemote()) {
            // SYNC_LOCAL: a plain local copy.
            ($this->log)('Copying dump locally');
            $this->runners->local()->run(sprintf('cp %s %s', escapeshellarg($originGz), escapeshellarg($targetGz)));

            return;
        }

        if (!$config->useRsync) {
            ($this->log)('Transferring dump via SFTP');
            $this->sftp->transfer($config, $originGz, $targetGz);

            return;
        }

        if (SyncMode::Proxy === $mode) {
            ($this->log)('Transferring dump via proxy (origin → local → target)');
            $this->proxy->transfer($config, $originGz, $targetGz);

            return;
        }

        if (SyncMode::SyncRemote === $mode) {
            ($this->log)('Copying dump on the remote host');
            $runner = $this->runners->forClient($config->origin, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
            $runner->run(sprintf('cp %s %s', escapeshellarg($originGz), escapeshellarg($targetGz)));

            return;
        }

        // RECEIVER pulls from the remote origin; SENDER pushes to the remote target.
        $remoteClient = $config->origin->isRemote() ? $config->origin : $config->target;
        $passwordEnvironment = $this->rsync->passwordEnvironment($remoteClient, $config->useSshpass);
        $authorization = $this->rsync->authorization($remoteClient, $config->useSshpass, $remoteClient->jumpHost);
        $options = $this->rsync->options($config->useRsyncOptions);

        $command = $this->rsync->build(
            $passwordEnvironment,
            $options,
            $authorization,
            $this->rsync->userHost($config->origin),
            $originGz,
            $this->rsync->userHost($config->target),
            $targetGz,
        );

        ($this->log)('Transferring dump');
        $this->logCommand($command);
        $this->runners->local()->run($command);
    }

    private function importDump(SyncConfig $config, SyncMode $mode, string $dumpName): void
    {
        $client = $config->target;
        $runner = $this->runners->forClient($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);

        $credentialsArg = $this->prepareCredentials($client, $runner);

        $dumpPath = $mode->isImport() && '' !== $config->importFile
            ? $config->importFile
            : $this->dumpDir($client).$dumpName.'.gz';

        if ($config->clearDatabase) {
            $this->clearDatabase($config, $runner, $credentialsArg);
        }

        $this->truncateTables($config, $runner, $credentialsArg);

        $command = $this->commands->importCommand('mysql', $credentialsArg, $client->db->name, 'gunzip', $dumpPath);

        ($this->log)('Importing dump into target');
        $this->logCommand($command);
        $runner->run($command);

        $this->runPostSql($client, $runner, $credentialsArg);
        $this->pruneDumps($client, $runner);
    }

    private function runPostSql(ClientConfig $client, CommandRunner $runner, string $credentialsArg): void
    {
        foreach ($client->postSql as $sql) {
            if ('' === $sql) {
                continue;
            }

            ($this->log)('Running post-import SQL');
            $runner->run($this->commands->execCommand('mysql', $credentialsArg, $client->db->name, $sql));
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

    private function ignoreOptions(SyncConfig $config): string
    {
        $options = [];
        foreach ($config->ignoreTables as $table) {
            if (str_contains($table, '*')) {
                continue; // wildcard expansion needs a live query — integration scope
            }
            $options[] = $this->tables->ignoreTableOption($config->origin->db->name, $table);
        }

        return implode(' ', $options);
    }

    private function prepareCredentials(ClientConfig $client, CommandRunner $runner): string
    {
        $content = $this->defaultsFile->buildContent($client->db);
        $path = $this->defaultsFile->generatePath();

        if ($client->isRemote()) {
            $runner->run($this->defaultsFile->remoteWriteCommand($content, $path));
        } else {
            file_put_contents($path, $content);
            chmod($path, 0o600);
        }

        return $this->credentials->defaultsExtraFileArgument($path);
    }

    private function dumpDir(ClientConfig $client): string
    {
        return rtrim($client->dumpDir, '/').'/';
    }

    private function logCommand(string $command): void
    {
        ($this->log)('  $ '.LogSanitizer::sanitize($command));
    }
}
