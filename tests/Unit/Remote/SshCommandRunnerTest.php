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

use KonradMichalik\SyncTool\Config\ClientConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\{LocalCommandRunner, RunnerFactory, SshCommandRunner};
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

        $this->expectException(SyncException::class);
        (new SshCommandRunner($ssh))->run('boom');
    }

    #[Test]
    public function nonZeroExitStatusThrows(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('partial output');
        $ssh->method('getExitStatus')->willReturn(1);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('status 1');

        (new SshCommandRunner($ssh))->run('false');
    }

    /**
     * The failing command is quoted back to the user, and the one that writes the
     * credential file to a remote host carries the password in its payload.
     */
    #[Test]
    public function theFailureMessageMasksCredentialsInTheCommand(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('');
        $ssh->method('getExitStatus')->willReturn(1);

        try {
            (new SshCommandRunner($ssh))->run("mysql -h db -u root -p's3cret' --defaults-extra-file=/tmp/.my_a.cnf");
            self::fail('Expected a SyncException');
        } catch (SyncException $exception) {
            self::assertStringNotContainsString('s3cret', $exception->getMessage());
            self::assertStringNotContainsString('/tmp/.my_a.cnf', $exception->getMessage());
        }
    }

    #[Test]
    public function runnerFactoryReturnsLocalRunnerForLocalClient(): void
    {
        $factory = new RunnerFactory();

        self::assertInstanceOf(LocalCommandRunner::class, $factory->local());
        self::assertInstanceOf(LocalCommandRunner::class, $factory->forClient(new ClientConfig(path: '/var/www')));
    }
}
