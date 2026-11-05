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

use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\{LogChannel, OutputMode};
use KonradMichalik\SyncTool\Exception\{ConfigException, SyncException, SyncToolException};
use KonradMichalik\SyncTool\Logging\LogWriter;
use KonradMichalik\SyncTool\Output\ConsoleReporter;
use KonradMichalik\SyncTool\Remote\RunnerFactory;
use KonradMichalik\SyncTool\Sync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function in_array;
use function sprintf;

/**
 * AnonymizeCommand.
 *
 * Applies the target's `anonymize` rules to the database as it is, for copies
 * that already hold production data. Configuration is resolved exactly as for
 * a sync; only the target is ever contacted.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'anonymize', description: 'Anonymize the target database in place, without a sync.')]
final class AnonymizeCommand extends SyncCommand
{
    /**
     * Inherited from `sync` but meaningless here. Accepting them silently would
     * suggest a narrower masking than the one that runs, e.g. `--tables`.
     */
    private const SYNC_ONLY_OPTIONS = [
        'reverse', 'import-file', 'dump-name', 'keep-dump', 'clear-database', 'tables', 'where',
        'additional-dump-options', 'no-rsync', 'use-rsync', 'no-check-dump', 'with-files', 'files-only',
        'files-target', 'check-for-updates', 'use-rsync-options', 'target-after-dump',
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $mode = $this->outputMode($input);
            $reporter = new ConsoleReporter($mode, $io, $output);
            $this->assertNoSyncOnlyOptions($input);

            $config = $this->buildConfigReportingDeprecations($input, $output, $reporter);

            $this->validator->validate($config);
            $syncConfig = SyncConfig::fromArray($config);
            $this->assertAnonymizable($syncConfig->target);

            if ($syncConfig->dryRun) {
                $reporter->success('Dry run: configuration resolved and validated, no changes made.');

                return Command::SUCCESS;
            }

            if (!$syncConfig->yes && $input->isInteractive() && OutputMode::Quiet !== $mode
                && !$this->confirm($io, $input, $this->confirmationText($syncConfig->target))
            ) {
                $reporter->success('Aborted by user.');

                return Command::SUCCESS;
            }

            $fileLog = new LogWriter($syncConfig->jsonLog, $syncConfig->logFile, static function (string $l): void {});
            $log = static function (string $m, LogChannel $channel = LogChannel::Step) use ($reporter, $fileLog): void {
                $reporter->step($m, $channel);
                $fileLog->log($m);
            };

            (new Sync(runners: new RunnerFactory(log: $log), log: $log))->anonymize($syncConfig);
            $reporter->success('Anonymization complete');

            return Command::SUCCESS;
        } catch (SyncToolException $e) {
            $reporter ??= new ConsoleReporter(OutputMode::Interactive, $io, $output);
            $reporter->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function assertNoSyncOnlyOptions(InputInterface $input): void
    {
        foreach (self::SYNC_ONLY_OPTIONS as $option) {
            if (!in_array($input->getOption($option), [false, null], true)) {
                throw new ConfigException(sprintf('--%s has no effect on anonymize. Drop it, it only applies to a sync.', $option));
            }
        }
    }

    private function assertAnonymizable(ClientConfig $target): void
    {
        if ($target->isRemote()) {
            throw new ConfigException(sprintf('The anonymize command only anonymizes a local target, %s is remote.', $target->host));
        }

        if ([] === $target->anonymize) {
            throw new ConfigException('No anonymize rules configured for the target. Add an `anonymize` block or `preset` to it.');
        }

        if ($target->protect) {
            throw new SyncException('The local target is protected against anonymization. Please check the target or adjust the host configuration.');
        }
    }

    private function confirmationText(ClientConfig $target): string
    {
        // With a `path` the name is detected later, or is only a variable mapping
        // (TYPO3 `.env`), so it is quoted only when it was written out literally.
        return '' === $target->path && '' !== $target->db->name
            ? sprintf('This irreversibly anonymizes the local database "%s". Continue?', $target->db->name)
            : 'This irreversibly anonymizes the local database. Continue?';
    }
}
