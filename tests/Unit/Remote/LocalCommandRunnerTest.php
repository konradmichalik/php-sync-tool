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

namespace MoveElevator\DbSyncTool\Tests\Unit\Remote;

use MoveElevator\DbSyncTool\Exception\DbSyncException;
use MoveElevator\DbSyncTool\Remote\LocalCommandRunner;
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
        $this->expectException(DbSyncException::class);

        (new LocalCommandRunner())->run('echo boom >&2; exit 1');
    }

    #[Test]
    public function failingCommandIsSwallowedWhenAllowed(): void
    {
        self::assertSame('', (new LocalCommandRunner())->run('echo boom >&2; exit 1', true));
    }
}
