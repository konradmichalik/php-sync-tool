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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote;

use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\LocalCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LocalCommandRunnerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LocalCommandRunnerTest extends TestCase
{
    #[Test]
    public function runReturnsTrimmedStdout(): void
    {
        self::assertSame('hello world', (new LocalCommandRunner())->run('echo "hello world"'));
    }

    #[Test]
    public function failingCommandThrows(): void
    {
        $this->expectException(SyncException::class);

        (new LocalCommandRunner())->run('echo boom >&2; exit 1');
    }

    #[Test]
    public function failingCommandIsSwallowedWhenAllowed(): void
    {
        self::assertSame('', (new LocalCommandRunner())->run('echo boom >&2; exit 1', true));
    }

    /**
     * A non-zero exit is a failure even from a command that says nothing about it.
     * Requiring stderr let silent failures through as successes.
     */
    #[Test]
    public function silentlyFailingCommandThrowsWithItsExitCode(): void
    {
        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('status 3');

        (new LocalCommandRunner())->run('exit 3');
    }

    #[Test]
    public function theFailureMessageMasksCredentials(): void
    {
        try {
            (new LocalCommandRunner())->run("exit 1 # mysql -p's3cret'");
            self::fail('Expected a SyncException');
        } catch (SyncException $exception) {
            self::assertStringNotContainsString('s3cret', $exception->getMessage());
        }
    }

    #[Test]
    public function streamsOutputToTheCallbackWhileTheCommandRuns(): void
    {
        $chunks = [];

        (new LocalCommandRunner())->run(
            "printf 'one'; printf 'two'",
            onOutput: static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        self::assertSame('onetwo', implode('', $chunks));
    }
}
