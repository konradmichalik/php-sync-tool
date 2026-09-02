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

namespace KonradMichalik\SyncTool\Output;

use Closure;
use KonradMichalik\SyncTool\Enum\{LogChannel, OutputMode};
use KonradMichalik\SyncTool\Output\Progress\{LiveSyncProgress, NullSyncProgress, SyncProgress};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface, StreamOutput};
use Symfony\Component\Console\Style\SymfonyStyle;

use function date;
use function json_encode;
use function sprintf;

use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * ConsoleReporter.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConsoleReporter
{
    /** @var Closure(): string */
    private readonly Closure $clock;

    private ?SyncProgress $progress = null;

    /**
     * @param Closure(): string|null $clock
     */
    public function __construct(
        private readonly OutputMode $mode,
        private readonly SymfonyStyle $io,
        private readonly OutputInterface $output,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): string => date(DATE_ATOM);
    }

    /**
     * @param string $mode        the plan's label, e.g. `RECEIVER`
     * @param string $description the direction it stands for, e.g. `(REMOTE ➔ LOCAL)`
     */
    public function summary(string $mode, string $description, string $origin, string $target): void
    {
        $full = sprintf('%s %s', $mode, $description);

        match ($this->mode) {
            OutputMode::Interactive => $this->interactiveSummary($mode, $origin, $target),
            OutputMode::Ci => $this->ciSummary($full, $origin, $target),
            OutputMode::Json => $this->output->writeln($this->jsonLine('summary', null, [
                'mode' => $full,
                'origin' => $origin,
                'target' => $target,
            ])),
            OutputMode::Quiet => null,
        };
    }

    /**
     * Live progress is an interactive-only concern: CI and JSON consumers parse
     * lines, and Quiet asked for silence. Progress renders on the error stream
     * so that piped stdout stays clean.
     */
    public function progress(int $totalSteps): SyncProgress
    {
        return $this->progress = $this->createProgress($totalSteps);
    }

    public function step(string $message, LogChannel $channel = LogChannel::Step): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->interactiveStep($message, $channel),
            OutputMode::Ci => $this->output->writeln($message),
            OutputMode::Json => $this->output->writeln($this->jsonLine('step', $message)),
            OutputMode::Quiet => null,
        };
    }

    public function success(string $message): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->interactiveSuccess($message),
            OutputMode::Ci => $this->output->writeln($message),
            OutputMode::Json => $this->output->writeln($this->jsonLine('success', $message)),
            OutputMode::Quiet => null,
        };
    }

    public function error(string $message): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->io->error($message),
            OutputMode::Ci => $this->output->writeln(sprintf('ERROR: %s', $message)),
            OutputMode::Json => $this->output->writeln($this->jsonLine('error', $message)),
            OutputMode::Quiet => $this->output->writeln($message, OutputInterface::VERBOSITY_QUIET),
        };
    }

    private function createProgress(int $totalSteps): SyncProgress
    {
        if (OutputMode::Interactive !== $this->mode) {
            return new NullSyncProgress();
        }

        $target = $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;

        if (!$target instanceof StreamOutput) {
            return new NullSyncProgress();
        }

        return new LiveSyncProgress($totalSteps, $target->getStream());
    }

    /**
     * The progress line carries the phase, so interactive runs stay compact:
     * -v adds what the tool is doing, -vv the commands it runs. A running live
     * line prints them above itself instead of being overwritten.
     */
    private function interactiveStep(string $message, LogChannel $channel): void
    {
        $wanted = match ($channel) {
            LogChannel::Step => $this->output->isVerbose(),
            LogChannel::Command => $this->output->isVeryVerbose(),
            LogChannel::Warning => true,
        };

        if (!$wanted) {
            return;
        }

        if (LogChannel::Warning === $channel) {
            $message = sprintf('<comment>[warning]</comment> %s', $message);
        }

        if (null !== $this->progress && $this->progress->enabled()) {
            $this->progress->log($message);

            return;
        }

        $this->io->text($message);
    }

    /**
     * A finished live line already ends in a confirmation, so repeating it in a
     * block would say the same thing twice and three lines taller. Without a live
     * line (piped output, an unsupported terminal, or an outcome reached before
     * the progress display exists, such as a dry run) this is the only
     * confirmation there is, so it prints as one plain line.
     */
    private function interactiveSuccess(string $message): void
    {
        if (null !== $this->progress && $this->progress->enabled()) {
            return;
        }

        $this->io->writeln(sprintf('<info>[ok]</info> %s', $message));
    }

    /**
     * One heading line naming the tool and where the data is about to move. The
     * mode label is held back until `-v`: on the default line it would repeat the
     * direction the two endpoints already spell out.
     */
    private function interactiveSummary(string $mode, string $origin, string $target): void
    {
        $endpoints = sprintf('%s ➔ %s', $origin, $target);

        $this->io->writeln([
            $this->output->isVerbose()
                ? sprintf('<info>php-sync-tool</info>  <comment>%s</comment>  %s', $mode, $endpoints)
                : sprintf('<info>php-sync-tool</info>  %s', $endpoints),
            '',
        ]);
    }

    private function ciSummary(string $mode, string $origin, string $target): void
    {
        $this->output->writeln(sprintf('mode: %s', $mode));
        $this->output->writeln(sprintf('origin: %s', $origin));
        $this->output->writeln(sprintf('target: %s', $target));
    }

    /**
     * @param array<string, string>|null $extra
     */
    private function jsonLine(string $event, ?string $message, ?array $extra = null): string
    {
        $data = ['time' => ($this->clock)(), 'event' => $event];

        if (null !== $message) {
            $data['message'] = $message;
        }

        if (null !== $extra) {
            $data = array_merge($data, $extra);
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
