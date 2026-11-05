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

    /**
     * phpseclib swallows its own timeout and hands back whatever output arrived,
     * with no exit status. That must not pass as a finished command.
     */
    #[Test]
    public function aTimedOutCommandCarriesTheRemoteErrorSoFar(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('');
        $ssh->method('isTimeout')->willReturn(true);
        $ssh->method('getStdError')->willReturn('mysqldump: Error 1878: Disk quota exceeded');

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('Disk quota exceeded');

        (new SshCommandRunner($ssh))->run('mysqldump db > /tmp/db.sql');
    }

    #[Test]
    public function aTimedOutCommandThrows(): void
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('partial output');
        $ssh->method('isTimeout')->willReturn(true);
        $ssh->method('getExitStatus')->willReturn(false);

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('Remote command timed out');

        (new SshCommandRunner($ssh))->run('mysqldump db > /tmp/db.sql');
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
     * Without the remote's own error, "exited with status 1" leaves the user to
     * rerun the command by hand to learn it was, say, an access denial.
     */
    #[Test]
    public function theFailureMessageCarriesTheRemoteError(): void
    {
        $ssh = $this->failingSsh("ERROR 1045 (28000): Access denied for user 'app'@'localhost'\n");

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage("Remote command exited with status 1: mysql app < dump.sql\nERROR 1045 (28000): Access denied for user 'app'@'localhost'");

        (new SshCommandRunner($ssh))->run('mysql app < dump.sql');
    }

    #[Test]
    public function theRemoteErrorIsMaskedLikeTheCommand(): void
    {
        $ssh = $this->failingSsh("bash: line 1: mysql -p's3cret': command not found");

        try {
            (new SshCommandRunner($ssh))->run('true');
            self::fail('Expected a SyncException');
        } catch (SyncException $exception) {
            self::assertStringContainsString('command not found', $exception->getMessage());
            self::assertStringNotContainsString('s3cret', $exception->getMessage());
        }
    }

    /**
     * The failing command is quoted back to the user, and the one that writes the
     * credential file to a remote host carries the password in its payload.
     */
    #[Test]
    public function theFailureMessageMasksCredentialsInTheCommand(): void
    {
        $ssh = $this->failingSsh();

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

    private function failingSsh(string $stderr = ''): SSH2
    {
        $ssh = $this->createStub(SSH2::class);
        $ssh->method('exec')->willReturn('');
        $ssh->method('getExitStatus')->willReturn(1);
        $ssh->method('getStdError')->willReturn($stderr);

        return $ssh;
    }
}
