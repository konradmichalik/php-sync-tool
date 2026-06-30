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

namespace KonradMichalik\SyncTool\Command;

use KonradMichalik\SyncTool\Config\{ConfigLoader, ConfigResolver, ConfigValidator, SyncConfig};
use KonradMichalik\SyncTool\Exception\SyncToolException;
use KonradMichalik\SyncTool\Logging\LogWriter;
use KonradMichalik\SyncTool\Mode\SyncModeResolver;
use KonradMichalik\SyncTool\Sync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;
use function sprintf;

/**
 * SyncCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'sync', description: 'Synchronize a database (and optionally files) between systems.')]
final class SyncCommand extends Command
{
    public function __construct(
        private readonly ConfigResolver $resolver = new ConfigResolver(),
        private readonly ConfigLoader $loader = new ConfigLoader(),
        private readonly ConfigValidator $validator = new ConfigValidator(),
        private readonly SyncModeResolver $modeResolver = new SyncModeResolver(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('origin', InputArgument::OPTIONAL, 'Origin host name from the host file')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target host name from the host file')
            ->addOption('config-file', 'f', InputOption::VALUE_REQUIRED, 'Path to a configuration file')
            ->addOption('mute', 'm', InputOption::VALUE_NONE, 'Mute console output')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the import confirmation prompt')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve and report without export/transfer/import')
            ->addOption('reverse', 'r', InputOption::VALUE_NONE, 'Swap origin and target')
            ->addOption('import-file', 'i', InputOption::VALUE_REQUIRED, 'Import from an existing dump file')
            ->addOption('dump-name', null, InputOption::VALUE_REQUIRED, 'Custom dump file name')
            ->addOption('keep-dump', null, InputOption::VALUE_NONE, 'Keep the dump and skip the import')
            ->addOption('clear-database', null, InputOption::VALUE_NONE, 'Drop all tables before import')
            ->addOption('tables', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of tables to sync')
            ->addOption('where', null, InputOption::VALUE_REQUIRED, 'WHERE clause for mysqldump')
            ->addOption('additional-mysqldump-options', null, InputOption::VALUE_REQUIRED, 'Extra mysqldump options')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Framework: TYPO3|Symfony|Drupal|WordPress|Laravel')
            ->addOption('no-rsync', null, InputOption::VALUE_NONE, 'Disable rsync (use SFTP fallback)')
            ->addOption('with-files', null, InputOption::VALUE_NONE, 'Enable file synchronization alongside the database')
            ->addOption('files-only', null, InputOption::VALUE_NONE, 'Synchronize only files, skip the database')
            ->addOption('log-file', 'l', InputOption::VALUE_REQUIRED, 'Write log output to a file')
            ->addOption('json-log', null, InputOption::VALUE_NONE, 'Format log output as JSON lines')
            ->addOption('host-file', 'o', InputOption::VALUE_REQUIRED, 'Additional hosts file to merge')
            ->addOption('force-password', null, InputOption::VALUE_NONE, 'Force interactive password authentication')
            ->addOption('use-rsync-options', null, InputOption::VALUE_REQUIRED, 'Additional rsync options');

        foreach (['origin', 'target'] as $prefix) {
            foreach ([...array_keys(EndpointOverrides::SUFFIX_MAP), ...array_keys(EndpointOverrides::DB_SUFFIX_MAP)] as $suffix) {
                $this->addOption(
                    $prefix.'-'.$suffix,
                    null,
                    InputOption::VALUE_REQUIRED,
                    sprintf('Override %s %s', $prefix, str_replace('-', ' ', $suffix)),
                );
            }
        }

        $this->addOption('target-after-dump', null, InputOption::VALUE_REQUIRED, 'Additional dump to import on the target after the main import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $config = $this->buildConfig($input, $output);
            $this->validator->validate($config);

            $syncConfig = SyncConfig::fromArray($config);
            $mode = $this->modeResolver->resolve($syncConfig);
            $this->modeResolver->checkForProtection($mode, $syncConfig);

            $io->title('php-sync-tool');
            $io->definitionList(
                ['Sync mode' => $mode->value.' '.$mode->description()],
                ['Origin' => $this->describeClient($syncConfig->origin->isRemote(), $syncConfig->origin->host)],
                ['Target' => $this->describeClient($syncConfig->target->isRemote(), $syncConfig->target->host)],
            );

            if ($syncConfig->dryRun) {
                $io->success('Dry run: configuration resolved and validated, no changes made.');

                return Command::SUCCESS;
            }

            $console = $syncConfig->jsonLog
                ? static function (string $line) use ($output): void { $output->writeln($line); }
            : static function (string $line) use ($io): void { $io->text($line); };
            $logWriter = new LogWriter($syncConfig->jsonLog, $syncConfig->logFile, $console);

            $sync = new Sync(log: $logWriter->log(...));
            $sync->run($syncConfig, $mode);

            $io->success('Synchronization complete.');

            return Command::SUCCESS;
        } catch (SyncToolException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(InputInterface $input, OutputInterface $output): array
    {
        /** @var string|null $configFile */
        $configFile = $input->getOption('config-file');
        /** @var string|null $origin */
        $origin = $input->getArgument('origin');
        /** @var string|null $target */
        $target = $input->getArgument('target');
        /** @var string|null $hostFile */
        $hostFile = $input->getOption('host-file');

        $resolved = $this->resolver->resolve($configFile, $origin, $target, $hostFile);

        if (str_starts_with($resolved->source, 'explicit file') && null !== $resolved->configFile) {
            $base = $this->loader->load($resolved->configFile);
        } else {
            $base = $resolved->mergedConfig;
            if ([] !== $resolved->originConfig) {
                $base['origin'] = array_merge($this->asArray($base['origin'] ?? null), $resolved->originConfig);
            }
            if ([] !== $resolved->targetConfig) {
                $base['target'] = array_merge($this->asArray($base['target'] ?? null), $resolved->targetConfig);
            }
        }

        $config = array_merge($base, $this->cliOverrides($input, $output));

        $config['origin'] = $this->mergeEndpoint(
            $this->asArray($config['origin'] ?? null),
            EndpointOverrides::build($this->endpointRaw($input, 'origin')),
        );
        $config['target'] = $this->mergeEndpoint(
            $this->asArray($config['target'] ?? null),
            EndpointOverrides::build($this->endpointRaw($input, 'target')),
        );

        /** @var string|null $afterDump */
        $afterDump = $input->getOption('target-after-dump');
        if (null !== $afterDump) {
            $config['target']['after_dump'] = $afterDump;
        }

        if (true === ($config['reverse'] ?? false)) {
            $config = $this->swapEndpoints($config);
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function cliOverrides(InputInterface $input, OutputInterface $output): array
    {
        $overrides = [];

        if ($output->isVerbose()) {
            $overrides['verbose'] = true;
        }
        if (true === $input->getOption('no-rsync')) {
            $overrides['use_rsync'] = false;
        }

        $booleanFlags = [
            'mute' => 'mute',
            'yes' => 'yes',
            'reverse' => 'reverse',
            'dry-run' => 'dry_run',
            'keep-dump' => 'keep_dump',
            'clear-database' => 'clear_database',
            'with-files' => 'with_files',
            'files-only' => 'files_only',
            'json-log' => 'json_log',
            'force-password' => 'force_password',
        ];
        foreach ($booleanFlags as $option => $key) {
            if (true === $input->getOption($option)) {
                $overrides[$key] = true;
            }
        }

        foreach ([
            'import-file' => 'import',
            'dump-name' => 'dump_name',
            'tables' => 'tables',
            'where' => 'where',
            'additional-mysqldump-options' => 'additional_mysqldump_options',
            'type' => 'type',
            'log-file' => 'log_file',
            'use-rsync-options' => 'use_rsync_options',
        ] as $option => $key) {
            $value = $input->getOption($option);
            if (null !== $value) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function swapEndpoints(array $config): array
    {
        $origin = $config['origin'] ?? [];
        $config['origin'] = $config['target'] ?? [];
        $config['target'] = $origin;

        return $config;
    }

    /**
     * @return array<string, string|null>
     */
    private function endpointRaw(InputInterface $input, string $prefix): array
    {
        $raw = [];
        foreach ([...array_keys(EndpointOverrides::SUFFIX_MAP), ...array_keys(EndpointOverrides::DB_SUFFIX_MAP)] as $suffix) {
            /** @var string|null $value */
            $value = $input->getOption($prefix.'-'.$suffix);
            $raw[$suffix] = $value;
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function mergeEndpoint(array $base, array $override): array
    {
        if (isset($override['db'])) {
            $override['db'] = array_merge($this->asArray($base['db'] ?? null), $this->asArray($override['db']));
        }

        return array_merge($base, $override);
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function describeClient(bool $isRemote, string $host): string
    {
        return $isRemote ? sprintf('remote (%s)', $host) : 'local';
    }
}
