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
use KonradMichalik\SyncTool\Enum\{LogChannel, OutputMode};
use KonradMichalik\SyncTool\Exception\SyncToolException;
use KonradMichalik\SyncTool\Logging\LogWriter;
use KonradMichalik\SyncTool\Mode\{SyncModeResolver, SyncPlan, SyncSteps};
use KonradMichalik\SyncTool\Output\ConsoleReporter;
use KonradMichalik\SyncTool\Output\Progress\NullSyncProgress;
use KonradMichalik\SyncTool\Sync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;
use function is_string;
use function sprintf;

/**
 * SyncCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'sync', description: 'Synchronize a database (and optionally files) between systems.')]
// Deliberately not final: PullCommand and PushCommand are this pipeline with a
// different endpoint assembly, and they override buildConfig() to provide it.

/**
 * SyncCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class SyncCommand extends Command
{
    public function __construct(
        protected readonly ConfigResolver $resolver = new ConfigResolver(),
        private readonly ConfigLoader $loader = new ConfigLoader(),
        private readonly ConfigValidator $validator = new ConfigValidator(),
        private readonly SyncModeResolver $modeResolver = new SyncModeResolver(),
        private readonly SyncSteps $steps = new SyncSteps(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('origin', InputArgument::OPTIONAL, 'Origin host name from the host file')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target host name from the host file');

        $this->configureSharedOptions();
    }

    /**
     * Every option that is not about which endpoints to use. `pull` and `push`
     * take the same set, they only name their endpoints differently.
     */
    protected function configureSharedOptions(): void
    {
        $this
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
            ->addOption('use-rsync-options', null, InputOption::VALUE_REQUIRED, 'Additional rsync options')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output mode: interactive|ci|json|quiet', 'interactive');

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
        $progress = new NullSyncProgress();

        try {
            /** @var string|null $outputOption */
            $outputOption = $input->getOption('output');
            $mode = OutputMode::fromString($outputOption);
            // `--quiet` belongs to the application, so it is absent when a command
            // runs standalone.
            if (($input->hasOption('quiet') && true === $input->getOption('quiet')) || true === $input->getOption('mute')) {
                $mode = OutputMode::Quiet;
            }

            $reporter = new ConsoleReporter($mode, $io, $output);

            $config = $this->buildConfig($input, $output);
            $this->validator->validate($config);

            $syncConfig = SyncConfig::fromArray($config);
            $plan = $this->modeResolver->resolve($syncConfig);
            $this->modeResolver->checkForProtection($plan, $syncConfig);

            $reporter->summary(
                $plan->label().' '.$plan->description(),
                $this->describeClient($syncConfig->origin->isRemote(), $syncConfig->origin->host),
                $this->describeClient($syncConfig->target->isRemote(), $syncConfig->target->host),
            );

            if ($syncConfig->dryRun) {
                $reporter->success('Dry run: configuration resolved and validated, no changes made.');

                return Command::SUCCESS;
            }

            if ($this->needsConfirmation($plan, $syncConfig, $mode, $input->isInteractive())
                && !$io->confirm(sprintf('This overwrites the %s database. Continue?', $this->describeClient($syncConfig->target->isRemote(), $syncConfig->target->host)), false)
            ) {
                $reporter->success('Aborted by user.');

                return Command::SUCCESS;
            }

            $fileLog = new LogWriter($syncConfig->jsonLog, $syncConfig->logFile, static function (string $l): void {});

            $progress = $reporter->progress($this->steps->count($syncConfig, $plan));

            $sync = new Sync(
                log: static function (string $m, LogChannel $channel = LogChannel::Step) use ($reporter, $fileLog): void {
                    $reporter->step($m, $channel);
                    $fileLog->log($m);
                },
                progress: $progress,
            );

            $sync->run($syncConfig, $plan);

            $progress->succeed('Synchronization complete');
            $reporter->success('Synchronization complete.');

            return Command::SUCCESS;
        } catch (SyncToolException $e) {
            // Anything outside our own hierarchy is left to Symfony; php-progress
            // stops the line from its destructor either way.
            $progress->fail('Synchronization failed');
            $reporter ??= new ConsoleReporter(OutputMode::Interactive, $io, $output);
            $reporter->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildConfig(InputInterface $input, OutputInterface $output): array
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

        return $this->finishConfig($base, $input, $output);
    }

    /**
     * Host links, CLI overrides and endpoint flags, applied the same way no
     * matter where the two endpoints came from.
     *
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    protected function finishConfig(array $base, InputInterface $input, OutputInterface $output): array
    {
        $base = $this->applyHostLinks($base);

        $config = array_merge($base, $this->cliOverrides($input, $output));

        $config = $this->applyEndpoint($config, 'origin', $input);
        $config = $this->applyEndpoint($config, 'target', $input);

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
    protected function cliOverrides(InputInterface $input, OutputInterface $output): array
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
    protected function swapEndpoints(array $config): array
    {
        $origin = $config['origin'] ?? [];
        $config['origin'] = $config['target'] ?? [];
        $config['target'] = $origin;

        return $config;
    }

    /**
     * Resolve `link: "@host"` references on origin/target to the linked host's
     * config (merged under any other keys the endpoint already sets).
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function applyHostLinks(array $config): array
    {
        foreach (['origin', 'target'] as $key) {
            $endpoint = $this->asArray($config[$key] ?? null);
            $link = $endpoint['link'] ?? null;

            if (is_string($link) && str_starts_with($link, '@')) {
                unset($endpoint['link']);
                $config[$key] = array_merge($this->resolver->resolveHostLink($link), $endpoint);
            }
        }

        return $config;
    }

    /**
     * Merge an endpoint's config + CLI overrides; leave it absent when empty so
     * the JSON schema never sees an empty array (`[]`) where it expects an object.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function applyEndpoint(array $config, string $key, InputInterface $input): array
    {
        $merged = $this->mergeEndpoint(
            $this->asArray($config[$key] ?? null),
            EndpointOverrides::build($this->endpointRaw($input, $key)),
        );

        if ([] === $merged) {
            unset($config[$key]);

            return $config;
        }

        $config[$key] = $merged;

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    protected function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
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

    private function describeClient(bool $isRemote, string $host): string
    {
        return $isRemote ? sprintf('remote (%s)', $host) : 'local';
    }

    /**
     * A confirmation prompt is only warranted for writing modes on an interactive
     * terminal. Non-interactive runs (CI, Deployer), quiet output and the explicit
     * --yes flag all proceed without asking.
     */
    private function needsConfirmation(SyncPlan $plan, SyncConfig $syncConfig, OutputMode $outputMode, bool $isInteractive): bool
    {
        return $plan->isProtectable()
            && !$syncConfig->yes
            && $isInteractive
            && OutputMode::Quiet !== $outputMode;
    }
}
