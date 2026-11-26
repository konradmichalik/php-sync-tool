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

namespace KonradMichalik\SyncTool\Tests\Unit\Command;

use KonradMichalik\SyncTool\Command\{AnonymizeCommand, PullCommand, PushCommand, SyncCommand};
use KonradMichalik\SyncTool\Exception\InterruptedException;
use PHPUnit\Framework\Attributes\{DataProvider, RequiresPhpExtension, Test};
use PHPUnit\Framework\TestCase;

use function constant;

/**
 * TerminationSignalsTest.
 *
 * PHP does not unwind on SIGINT or SIGTERM, so without a handler the `finally`
 * blocks that remove credential files and staging data never run.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[RequiresPhpExtension('pcntl')]
final class TerminationSignalsTest extends TestCase
{
    /**
     * @return iterable<string, array{SyncCommand}>
     */
    public static function commands(): iterable
    {
        yield 'sync' => [new SyncCommand()];
        yield 'anonymize' => [new AnonymizeCommand()];
        yield 'pull' => [new PullCommand()];
        yield 'push' => [new PushCommand()];
    }

    #[Test]
    #[DataProvider('commands')]
    public function everyCommandThatTouchesADatabaseSubscribesToBothSignals(SyncCommand $command): void
    {
        self::assertSame([\SIGINT, \SIGTERM], $command->getSubscribedSignals());
    }

    #[Test]
    #[DataProvider('signals')]
    public function aSignalBecomesAnExceptionSoTheFinallyBlocksRun(string $name): void
    {
        $this->expectException(InterruptedException::class);
        $this->expectExceptionMessage('Interrupted by '.$name);

        (new SyncCommand())->handleSignal(constant($name));
    }

    /**
     * The first signal unwinds gracefully. A second one is the user insisting, so
     * it ends the run at once instead of waiting for a cleanup that may be stuck.
     */
    #[Test]
    public function aSecondSignalEndsTheRunAtOnce(): void
    {
        $command = new SyncCommand();

        try {
            $command->handleSignal(\SIGINT);
            self::fail('The first signal must interrupt the run');
        } catch (InterruptedException) {
        }

        self::assertSame(128 + \SIGTERM, $command->handleSignal(\SIGTERM));
    }

    /**
     * Names rather than constants: the provider runs before the extension
     * requirement is checked, and the constants only exist with pcntl.
     *
     * @return iterable<string, array{string}>
     */
    public static function signals(): iterable
    {
        yield 'interrupt' => ['SIGINT'];
        yield 'terminate' => ['SIGTERM'];
    }
}
