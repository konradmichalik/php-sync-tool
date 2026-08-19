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

namespace KonradMichalik\SyncTool\Tests\Unit\Output;

use KonradMichalik\SyncTool\Enum\{LogChannel, OutputMode};
use KonradMichalik\SyncTool\Output\ConsoleReporter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\{BufferedOutput, OutputInterface, StreamOutput};
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ConsoleReporterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConsoleReporterTest extends TestCase
{
    #[Test]
    public function ciEmitsPlainLines(): void
    {
        $out = new BufferedOutput();
        $r = $this->reporter(OutputMode::Ci, $out);
        $r->step('Importing dump');
        $r->success('done');
        $text = $out->fetch();
        self::assertStringContainsString('Importing dump', $text);
        self::assertStringContainsString('done', $text);
        self::assertStringNotContainsString('[OK]', $text); // no SymfonyStyle decoration
    }

    #[Test]
    public function jsonEmitsOneObjectPerEvent(): void
    {
        $out = new BufferedOutput();
        $this->reporter(OutputMode::Json, $out)->step('msg /a');
        self::assertSame('{"time":"T","event":"step","message":"msg /a"}'."\n", $out->fetch());
    }

    #[Test]
    public function jsonSummaryIncludesEndpoints(): void
    {
        $out = new BufferedOutput();
        $this->reporter(OutputMode::Json, $out)->summary('RECEIVER (REMOTE)', 'remote (h)', 'local');
        $line = $out->fetch();
        self::assertStringContainsString('"event":"summary"', $line);
        self::assertStringContainsString('remote (h)', $line);
    }

    #[Test]
    public function quietSuppressesEverythingButErrors(): void
    {
        $out = new BufferedOutput();
        $r = $this->reporter(OutputMode::Quiet, $out);
        $r->summary('RECEIVER', 'remote (h)', 'local');
        $r->step('working');
        $r->success('done');
        self::assertSame('', $out->fetch());
        $r->error('boom');
        self::assertStringContainsString('boom', $out->fetch());
    }

    #[Test]
    public function interactiveRendersProgressOnTheCommandStream(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $out = new StreamOutput($stream);
        $reporter = new ConsoleReporter(OutputMode::Interactive, new SymfonyStyle(new ArrayInput([]), $out), $out);

        $reporter->progress(2)->succeed('Dump written');

        rewind($stream);
        self::assertStringContainsString('Dump written', (string) stream_get_contents($stream));
    }

    #[Test]
    public function ciSuppressesProgressOutput(): void
    {
        $out = new BufferedOutput();

        $this->reporter(OutputMode::Ci, $out)->progress(2)->succeed('Transfer complete');

        self::assertSame('', $out->fetch());
    }

    #[Test]
    public function jsonSuppressesProgressOutput(): void
    {
        $out = new BufferedOutput();

        $progress = $this->reporter(OutputMode::Json, $out)->progress(2);
        $progress->phase('Transferring dump');
        $progress->advance();

        self::assertSame('', $out->fetch());
    }

    #[Test]
    public function interactiveStaysQuietAboutStepsUntilAskedForThem(): void
    {
        $out = new BufferedOutput();

        $reporter = $this->reporter(OutputMode::Interactive, $out);
        $reporter->step('Creating origin dump');
        $reporter->step('  $ mysqldump ...', LogChannel::Command);

        self::assertSame('', $out->fetch());
    }

    #[Test]
    public function verboseShowsThePhasesButNotTheCommands(): void
    {
        $out = new BufferedOutput();
        $out->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $reporter = $this->reporter(OutputMode::Interactive, $out);
        $reporter->step('Creating origin dump');
        $reporter->step('  $ mysqldump ...', LogChannel::Command);

        $text = $out->fetch();
        self::assertStringContainsString('Creating origin dump', $text);
        self::assertStringNotContainsString('mysqldump', $text);
    }

    #[Test]
    public function veryVerboseAlsoShowsTheCommands(): void
    {
        $out = new BufferedOutput();
        $out->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->reporter(OutputMode::Interactive, $out)->step('  $ mysqldump ...', LogChannel::Command);

        self::assertStringContainsString('mysqldump', $out->fetch());
    }

    #[Test]
    public function ciKeepsEveryLineRegardlessOfVerbosity(): void
    {
        $out = new BufferedOutput();

        $reporter = $this->reporter(OutputMode::Ci, $out);
        $reporter->step('Creating origin dump');
        $reporter->step('  $ mysqldump ...', LogChannel::Command);

        $text = $out->fetch();
        self::assertStringContainsString('Creating origin dump', $text);
        self::assertStringContainsString('mysqldump', $text);
    }

    #[Test]
    public function stepsGoThroughTheLiveLineOnceProgressIsRunning(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $out = new StreamOutput($stream, OutputInterface::VERBOSITY_VERBOSE);
        $reporter = new ConsoleReporter(OutputMode::Interactive, new SymfonyStyle(new ArrayInput([]), $out), $out);

        $reporter->progress(2);
        $reporter->step('Creating origin dump');

        rewind($stream);
        self::assertStringContainsString('Creating origin dump', (string) stream_get_contents($stream));
    }

    private function reporter(OutputMode $mode, BufferedOutput $out): ConsoleReporter
    {
        return new ConsoleReporter($mode, new SymfonyStyle(new ArrayInput([]), $out), $out, static fn (): string => 'T');
    }
}
