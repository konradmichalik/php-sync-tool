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

use MoveElevator\DbSyncTool\Config\ClientConfig;
use MoveElevator\DbSyncTool\Exception\DbSyncException;
use MoveElevator\DbSyncTool\Remote\{LocalCommandRunner, RunnerFactory, SshCommandRunner};
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * SshCommandRunnerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class SshCommandRunnerTest extends TestCase
{
    #[Test]
    public function runReturnsTrimmedOutputOnSuccess(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn("hello\n");
        $ssh->method('getExitStatus')->willReturn(0);

        self::assertSame('hello', (new SshCommandRunner($ssh))->run('echo hello'));
    }

    #[Test]
    public function falseOutputThrowsUnlessAllowed(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn(false);

        self::assertSame('', (new SshCommandRunner($ssh))->run('boom', true));

        $this->expectException(DbSyncException::class);
        (new SshCommandRunner($ssh))->run('boom');
    }

    #[Test]
    public function nonZeroExitStatusThrows(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('partial output');
        $ssh->method('getExitStatus')->willReturn(1);

        $this->expectException(DbSyncException::class);
        $this->expectExceptionMessage('status 1');

        (new SshCommandRunner($ssh))->run('false');
    }

    #[Test]
    public function runnerFactoryReturnsLocalRunnerForLocalClient(): void
    {
        $factory = new RunnerFactory();

        self::assertInstanceOf(LocalCommandRunner::class, $factory->local());
        self::assertInstanceOf(LocalCommandRunner::class, $factory->forClient(new ClientConfig(path: '/var/www')));
    }
}
