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

namespace KonradMichalik\SyncTool\Config;

use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function is_scalar;
use function sprintf;

/**
 * SyncConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SyncConfig
{
    /**
     * @param list<string>             $ignoreTables
     * @param list<string>             $truncateTables
     * @param list<FileTransferConfig> $files
     * @param array<string, string>    $scripts
     */
    public function __construct(
        public bool $verbose = false,
        public bool $mute = false,
        public bool $dryRun = false,
        public bool $yes = false,
        public bool $reverse = false,
        public bool $keepDump = false,
        public string $dumpName = '',
        public bool $checkDump = true,
        public bool $clearDatabase = false,
        public string $importFile = '',
        public string $tables = '',
        public string $where = '',
        public string $additionalMysqldumpOptions = '',
        public array $ignoreTables = [],
        public array $truncateTables = [],
        public bool $useRsync = true,
        public ?string $useRsyncOptions = null,
        public bool $useSshpass = false,
        public array $files = [],
        public ?string $filesOptions = null,
        public bool $withFiles = false,
        public bool $filesOnly = false,
        public bool $sshAgent = false,
        public bool $forcePassword = false,
        public bool $strictHostKeyChecking = true,
        public ?string $sshPasswordOrigin = null,
        public ?string $sshPasswordTarget = null,
        public string $linkHosts = '',
        public ?string $linkOrigin = null,
        public ?string $linkTarget = null,
        public ?string $configFilePath = null,
        public bool $isSameClient = false,
        public bool $defaultOriginDumpDir = true,
        public bool $defaultTargetDumpDir = true,
        public ?string $logFile = null,
        public bool $jsonLog = false,
        public ?string $type = null,
        public array $scripts = [],
        public ClientConfig $origin = new ClientConfig(),
        public ClientConfig $target = new ClientConfig(),
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sshPasswords = $data['ssh_password'] ?? null;
        $sshPasswords = is_array($sshPasswords) ? $sshPasswords : [];

        return new self(
            verbose: ConfigAccessor::getBool($data, 'verbose', false),
            mute: ConfigAccessor::getBool($data, 'mute', false),
            dryRun: ConfigAccessor::getBool($data, 'dry_run', false),
            yes: ConfigAccessor::getBool($data, 'yes', false),
            reverse: ConfigAccessor::getBool($data, 'reverse', false),
            keepDump: ConfigAccessor::getBool($data, 'keep_dump', false),
            dumpName: ConfigAccessor::getString($data, 'dump_name', ''),
            checkDump: ConfigAccessor::getBool($data, 'check_dump', true),
            clearDatabase: ConfigAccessor::getBool($data, 'clear_database', false),
            importFile: ConfigAccessor::getString($data, 'import', ''),
            tables: ConfigAccessor::getString($data, 'tables', ''),
            where: ConfigAccessor::getString($data, 'where', ''),
            additionalMysqldumpOptions: ConfigAccessor::getString($data, 'additional_mysqldump_options', ''),
            ignoreTables: ConfigAccessor::getStringList($data, 'ignore_tables', 'ignore_table'),
            truncateTables: ConfigAccessor::getStringList($data, 'truncate_tables', 'truncate_table'),
            useRsync: ConfigAccessor::getBool($data, 'use_rsync', true),
            useRsyncOptions: ConfigAccessor::getStringOrNull($data, 'use_rsync_options'),
            useSshpass: ConfigAccessor::getBool($data, 'use_sshpass', false),
            files: self::parseFilesConfig($data['files'] ?? null),
            filesOptions: self::parseFilesOptions($data['files'] ?? null, $data['files_options'] ?? null),
            withFiles: ConfigAccessor::getBool($data, 'with_files', false),
            filesOnly: ConfigAccessor::getBool($data, 'files_only', false),
            sshAgent: ConfigAccessor::getBool($data, 'ssh_agent', false),
            forcePassword: ConfigAccessor::getBool($data, 'force_password', false),
            strictHostKeyChecking: ConfigAccessor::getBool($data, 'ssh_strict_host_key_checking', true),
            sshPasswordOrigin: ConfigAccessor::getStringOrNull($sshPasswords, 'origin'),
            sshPasswordTarget: ConfigAccessor::getStringOrNull($sshPasswords, 'target'),
            linkHosts: ConfigAccessor::getString($data, 'link_hosts', ''),
            linkOrigin: ConfigAccessor::getStringOrNull($data, 'link_origin'),
            linkTarget: ConfigAccessor::getStringOrNull($data, 'link_target'),
            configFilePath: ConfigAccessor::getStringOrNull($data, 'config_file_path'),
            isSameClient: ConfigAccessor::getBool($data, 'is_same_client', false),
            defaultOriginDumpDir: ConfigAccessor::getBool($data, 'default_origin_dump_dir', true),
            defaultTargetDumpDir: ConfigAccessor::getBool($data, 'default_target_dump_dir', true),
            logFile: ConfigAccessor::getStringOrNull($data, 'log_file'),
            jsonLog: ConfigAccessor::getBool($data, 'json_log', false),
            type: ConfigAccessor::getStringOrNull($data, 'type'),
            scripts: ConfigAccessor::getStringMap($data, 'scripts'),
            origin: ClientConfig::fromArray(self::subArray($data, 'origin')),
            target: ClientConfig::fromArray(self::subArray($data, 'target')),
        );
    }

    public function withClients(ClientConfig $origin, ClientConfig $target): self
    {
        return new self(
            verbose: $this->verbose,
            mute: $this->mute,
            dryRun: $this->dryRun,
            yes: $this->yes,
            reverse: $this->reverse,
            keepDump: $this->keepDump,
            dumpName: $this->dumpName,
            checkDump: $this->checkDump,
            clearDatabase: $this->clearDatabase,
            importFile: $this->importFile,
            tables: $this->tables,
            where: $this->where,
            additionalMysqldumpOptions: $this->additionalMysqldumpOptions,
            ignoreTables: $this->ignoreTables,
            truncateTables: $this->truncateTables,
            useRsync: $this->useRsync,
            useRsyncOptions: $this->useRsyncOptions,
            useSshpass: $this->useSshpass,
            files: $this->files,
            filesOptions: $this->filesOptions,
            withFiles: $this->withFiles,
            filesOnly: $this->filesOnly,
            sshAgent: $this->sshAgent,
            forcePassword: $this->forcePassword,
            strictHostKeyChecking: $this->strictHostKeyChecking,
            sshPasswordOrigin: $this->sshPasswordOrigin,
            sshPasswordTarget: $this->sshPasswordTarget,
            linkHosts: $this->linkHosts,
            linkOrigin: $this->linkOrigin,
            linkTarget: $this->linkTarget,
            configFilePath: $this->configFilePath,
            isSameClient: $this->isSameClient,
            defaultOriginDumpDir: $this->defaultOriginDumpDir,
            defaultTargetDumpDir: $this->defaultTargetDumpDir,
            logFile: $this->logFile,
            jsonLog: $this->jsonLog,
            type: $this->type,
            scripts: $this->scripts,
            origin: $origin,
            target: $target,
        );
    }

    public function getClient(string $client): ClientConfig
    {
        return match ($client) {
            'origin' => $this->origin,
            'target' => $this->target,
            default => throw new InvalidArgumentException(sprintf('Unknown client: %s', $client)),
        };
    }

    /**
     * Parse files configuration, supporting both the new flat-list format
     * and the legacy nested format (files.config[]).
     *
     * @return list<FileTransferConfig>
     */
    private static function parseFilesConfig(mixed $filesData): array
    {
        if (!is_array($filesData) || [] === $filesData) {
            return [];
        }

        if (!array_is_list($filesData) && array_key_exists('config', $filesData)) {
            $config = $filesData['config'] ?? [];

            return self::mapFileEntries(is_array($config) ? $config : []);
        }

        if (array_is_list($filesData)) {
            return self::mapFileEntries($filesData);
        }

        return [];
    }

    /**
     * @param array<int|string, mixed> $entries
     *
     * @return list<FileTransferConfig>
     */
    private static function mapFileEntries(array $entries): array
    {
        return array_values(array_map(
            static fn (mixed $entry): FileTransferConfig => FileTransferConfig::fromArray(
                is_array($entry) ? $entry : null,
            ),
            $entries,
        ));
    }

    /**
     * Resolve global file-transfer rsync options. Direct files_options take
     * precedence (an explicit empty string overrides the legacy option list).
     */
    private static function parseFilesOptions(mixed $filesData, mixed $filesOptionsDirect): ?string
    {
        if (null !== $filesOptionsDirect) {
            return is_scalar($filesOptionsDirect) ? (string) $filesOptionsDirect : null;
        }

        if (is_array($filesData) && !array_is_list($filesData) && array_key_exists('option', $filesData)) {
            $options = $filesData['option'] ?? [];
            if (is_array($options) && [] !== $options) {
                return implode(' ', array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $options));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private static function subArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }
}
