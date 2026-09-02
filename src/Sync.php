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
use KonradMichalik\SyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
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

use function explode;
use function implode;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trim;

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
        $driver = $this->drivers->forDatabase($client->db, $client->console);
        $this->assertSupported($driver, $config, $client->db);

        $credentialsPath = $this->prepareCredentials($client, $runner, $driver);

        try {
            $command = $driver->dumpCommand(new DumpRequest(
                db: $client->db,
                credentialsPath: $credentialsPath,
                dumpFilePath: $this->dumpDir($client).$dumpName,
                exportTables: $this->tables->exportTables($config->tables),
                ignoreTables: $this->resolveIgnoreTables($config, $runner, $driver, $credentialsPath),
                where: $config->where,
                additionalOptions: $config->additionalDumpOptions,
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
            singleFile: true,
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
        $driver = $this->drivers->forDatabase($client->db, $client->console);
        $this->assertSupported($driver, $config, $client->db);

        $credentialsPath = $this->prepareCredentials($client, $runner, $driver);

        try {
            $dumpPath = $plan->isImport() && '' !== $config->importFile
                ? $config->importFile
                : $this->dumpDir($client).$dumpName.'.gz';

            if ($config->checkDump) {
                $this->checkDump($runner, $driver, $dumpPath);
            }

            if ($config->backupBeforeImport) {
                $this->backupTarget($config, $runner, $driver, $credentialsPath);
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

    /**
     * A copy of the target as it is now, taken before anything overwrites it. The
     * export filters are deliberately not applied: a partial sync still needs a
     * complete copy to go back to.
     */
    private function backupTarget(SyncConfig $config, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        $client = $config->target;
        $path = $this->dumpDir($client).$this->namer->generateBackup($config);

        $command = $driver->dumpCommand(new DumpRequest(
            db: $client->db,
            credentialsPath: $credentialsPath,
            dumpFilePath: $path,
        ));

        ($this->log)('Backing up the target database to '.$path.'.gz');
        $this->logCommand($command);
        $this->progress->phase('Backing up the target');
        $runner->run($command);
        $this->progress->advance();
    }

    /**
     * A dump is only trustworthy if the dump tool got to the end of it. Checking
     * the file size alone accepts the half-written file a mysqldump killed by a
     * full disk leaves behind, and the import that follows may already have
     * dropped the target's tables.
     *
     * The trailer is read rather than the last line alone, because pg_dump closes
     * its marker with a comment rule while mysqldump does not.
     */
    private function checkDump(CommandRunner $runner, DatabaseDriver $driver, string $dumpPath): void
    {
        $safePath = escapeshellarg($dumpPath);
        $compressed = str_ends_with($dumpPath, '.gz');

        if ('OK' !== trim($runner->run(sprintf('test -s %s && echo OK', $safePath), true))) {
            throw new SyncException(sprintf('Dump validation failed: %s is missing or empty', $dumpPath));
        }

        // gzip carries its own checksum, and it is the only thing that can tell a
        // stream that decompressed correctly from one that merely decompressed.
        // Reading the trailer cannot: `gunzip -c | tail` reports tail's exit
        // status, so an archive whose footer or CRC is damaged still produced its
        // last lines, marker included, and was accepted.
        //
        // This decompresses a second time, which is real work on a large dump. It
        // buys the difference between a dump that looks finished and one that is
        // intact, on the step whose whole job is to tell those apart. `check_dump`
        // turns the pair off together.
        if ($compressed && 'OK' !== trim($runner->run(sprintf('gunzip -t %s && echo OK', $safePath), true))) {
            throw new SyncException(sprintf('Dump validation failed: %s is not a valid gzip archive, it was damaged in writing or transfer', $dumpPath));
        }

        $read = $compressed ? 'gunzip -c '.$safePath : 'cat '.$safePath;
        $trailer = $runner->run(sprintf('%s | tail -n 5', $read), true);

        if (!$this->hasCompletionLine($trailer, $driver->dumpCompletionMarker())) {
            throw new SyncException(sprintf('Dump validation failed: %s is incomplete, the dump tool did not finish writing it. Set check_dump to false if the dump is intentionally written without comments.', $dumpPath));
        }
    }

    /**
     * Whether one of the trailing lines *is* the completion line, rather than
     * merely containing the marker somewhere.
     *
     * A row that happens to carry the marker text would satisfy a substring match
     * over the whole trailer and validate a truncated dump. It cannot open a line:
     * both dump tools escape newlines inside values, so data never reaches a line
     * start of its own.
     */
    private function hasCompletionLine(string $trailer, string $marker): bool
    {
        foreach (explode("\n", $trailer) as $line) {
            if (str_starts_with(trim($line), $marker)) {
                return true;
            }
        }

        return false;
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

        // One invocation for every rule: each extra exec is a full round trip to
        // the target, and a partially masked copy is worse than none.
        $command = $driver->execCommand($client->db, $credentialsPath, implode(' ', $statements));
        $this->logCommand($command);
        $runner->run($command);

        $this->progress->advance();
    }

    private function runPostSql(ClientConfig $client, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): void
    {
        $statements = array_filter($client->postSql, static fn (string $sql): bool => '' !== $sql);

        if ([] === $statements) {
            return;
        }

        ($this->log)('Running post-import SQL');
        $this->progress->phase('Running post-import SQL');

        $command = $driver->execCommand($client->db, $credentialsPath, implode(' ', $statements));
        $this->logCommand($command);
        $runner->run($command);

        $this->progress->advance();
    }

    private function pruneDumps(ClientConfig $client, CommandRunner $runner): void
    {
        if (null === $client->keepDumps) {
            return;
        }

        $isDarwin = !$client->isRemote() && 'Darwin' === \PHP_OS_FAMILY;
        // Only dumps this tool wrote. The glob used to be `*`, which made every
        // foreign .sql or .gz in the dump directory a deletion candidate.
        $listing = $runner->run(
            $this->dumps->listDumpsCommand('stat', 'sort', 'grep', $this->dumpDir($client).DumpFileNamer::PREFIX, $isDarwin),
            true,
        );

        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $listing))));
        $files = array_map($this->dumps->extractFilename(...), $lines);
        $obsolete = $this->dumps->filesToRemove($files, $client->keepDumps);

        if ([] === $obsolete) {
            return;
        }

        foreach ($obsolete as $file) {
            ($this->log)('Removing old dump '.$file);
        }

        $runner->run('rm -f '.implode(' ', array_map(escapeshellarg(...), $obsolete)), true);
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
        // Expanded against the target, which is the database about to be emptied.
        $tables = $this->expandPatterns($config->truncateTables, $config->target->db, $runner, $driver, $credentialsPath);
        $statement = $driver->truncateTablesStatement($tables);

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
        return $this->expandPatterns($config->ignoreTables, $config->origin->db, $runner, $driver, $credentialsPath);
    }

    /**
     * Turns `table*` entries into the table names they currently match, asking
     * the database that the caller is about to act on. Names without a wildcard
     * pass through untouched, so a table that does not exist yet stays in the
     * list and the driver decides what that means.
     *
     * Every wildcard is resolved by a single query. One query per pattern meant a
     * full round trip per entry, and a TYPO3 `truncate_tables` list of ten cache
     * patterns paid for ten of them before the first table was touched. A table
     * matched by two patterns now appears once instead of twice.
     *
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private function expandPatterns(array $patterns, DatabaseConfig $db, CommandRunner $runner, DatabaseDriver $driver, string $credentialsPath): array
    {
        $tables = [];
        $wildcards = [];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $wildcards[] = str_replace('*', '%', $pattern);

                continue;
            }

            $tables[] = $pattern;
        }

        if ([] === $wildcards) {
            return $tables;
        }

        $sql = $driver->listTablesMatchingSql($db->name, $wildcards);
        $output = $runner->run($driver->execCommand($db, $credentialsPath, $sql), true);

        foreach ($driver->parseTableList($output) as $match) {
            $tables[] = $match;
        }

        return $tables;
    }

    /**
     * Refuse a run that asks for something this database system cannot express,
     * before anything is dumped, transferred or imported.
     */
    private function assertSupported(DatabaseDriver $driver, SyncConfig $config, DatabaseConfig $db): void
    {
        $unsupported = $driver->unsupportedFeatures($config, $db);

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
            // Create the file empty, restrict it, then fill it. Writing first and
            // chmod'ing after left the password readable for everyone with access
            // to the dump directory for the duration of the write.
            touch($path);
            chmod($path, 0o600);
            file_put_contents($path, $content);
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
