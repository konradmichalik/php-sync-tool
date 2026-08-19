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
use KonradMichalik\SyncTool\Enum\OutputMode;
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
final readonly class ConsoleReporter
{
    /** @var Closure(): string */
    private Closure $clock;

    /**
     * @param Closure(): string|null $clock
     */
    public function __construct(
        private OutputMode $mode,
        private SymfonyStyle $io,
        private OutputInterface $output,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): string => date(DATE_ATOM);
    }

    public function summary(string $mode, string $origin, string $target): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->interactiveSummary($mode, $origin, $target),
            OutputMode::Ci => $this->ciSummary($mode, $origin, $target),
            OutputMode::Json => $this->output->writeln($this->jsonLine('summary', null, [
                'mode' => $mode,
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

    public function step(string $message): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->io->text($message),
            OutputMode::Ci => $this->output->writeln($message),
            OutputMode::Json => $this->output->writeln($this->jsonLine('step', $message)),
            OutputMode::Quiet => null,
        };
    }

    public function success(string $message): void
    {
        match ($this->mode) {
            OutputMode::Interactive => $this->io->success($message),
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

    private function interactiveSummary(string $mode, string $origin, string $target): void
    {
        $this->io->title('php-sync-tool');
        $this->io->definitionList(
            ['Sync mode' => $mode],
            ['Origin' => $origin],
            ['Target' => $target],
        );
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
