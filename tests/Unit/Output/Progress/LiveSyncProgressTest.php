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

namespace KonradMichalik\SyncTool\Tests\Unit\Output\Progress;

use KonradMichalik\PhpProgress\Terminal\Capabilities;
use KonradMichalik\SyncTool\Output\Progress\LiveSyncProgress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * LiveSyncProgressTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LiveSyncProgressTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+');

        if (false === $stream) {
            throw new RuntimeException('Unable to open the test stream.');
        }

        $this->stream = $stream;
    }

    #[Test]
    public function countsCompletedStepsAgainstTheTotal(): void
    {
        $progress = $this->progress(3);

        $progress->phase('Creating origin dump');
        $progress->advance();
        $progress->phase('Importing dump');
        $progress->advance();

        $output = $this->rendered();

        self::assertStringContainsString('phase=Creating origin dump', $output);
        self::assertStringContainsString('(1/3)', $output);
        self::assertStringContainsString('phase=Importing dump', $output);
        self::assertStringContainsString('(2/3)', $output);
    }

    #[Test]
    public function showsAndClearsDetailsSuchAsTheRsyncPercentage(): void
    {
        $progress = $this->progress(2);

        $progress->phase('Transferring dump.gz');
        $progress->detail('rsync', '45%');
        $progress->advance();
        $progress->detail('rsync', null);
        $progress->phase('Importing dump');
        $progress->advance();

        $lines = array_values(array_filter(explode("\n", $this->rendered()), static fn (string $line): bool => '' !== $line));

        self::assertStringContainsString('rsync=45%', implode("\n", $lines));
        self::assertStringNotContainsString('rsync=', (string) end($lines));
    }

    #[Test]
    public function printsLogLinesVerbatimSoTheyDoNotFightTheLiveLine(): void
    {
        $progress = $this->progress(1);
        $progress->phase('Creating origin dump');

        $progress->log('  $ mysqldump --defaults-extra-file=/tmp/x app');

        self::assertStringContainsString('  $ mysqldump --defaults-extra-file=/tmp/x app', $this->rendered());
    }

    #[Test]
    public function reportsSuccessAndFailureOfTheWholeRun(): void
    {
        $this->progress(1)->succeed('Synchronization complete');
        self::assertStringContainsString('[ok] Synchronization complete', $this->rendered());
    }

    #[Test]
    public function theFinalSuccessLineDropsTheRunningPhase(): void
    {
        $progress = $this->progress(1);
        $progress->phase('Importing dump');
        $progress->succeed('Synchronization complete');

        $lines = array_values(array_filter(explode("\n", $this->rendered()), static fn (string $line): bool => '' !== $line));

        self::assertStringNotContainsString('phase=', (string) end($lines));
    }

    #[Test]
    public function aFailedRunKeepsThePhaseItDiedIn(): void
    {
        $progress = $this->progress(1);
        $progress->phase('Importing dump');
        $progress->fail('Sync failed');

        $lines = array_values(array_filter(explode("\n", $this->rendered()), static fn (string $line): bool => '' !== $line));

        self::assertStringContainsString('phase=Importing dump', (string) end($lines));
    }

    #[Test]
    public function reportsAFailedRun(): void
    {
        $this->progress(1)->fail('Sync failed');
        self::assertStringContainsString('[fail] Sync failed', $this->rendered());
    }

    #[Test]
    public function reportsItselfAsEnabled(): void
    {
        self::assertTrue($this->progress(1)->enabled());
    }

    private function progress(int $totalSteps): LiveSyncProgress
    {
        return new LiveSyncProgress(
            $totalSteps,
            $this->stream,
            new Capabilities(tty: false, width: 120, colors: 'none', unicode: false),
        );
    }

    private function rendered(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }
}
