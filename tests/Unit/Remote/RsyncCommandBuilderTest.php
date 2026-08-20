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

use KonradMichalik\SyncTool\Config\{ClientConfig, JumpHostConfig};
use KonradMichalik\SyncTool\Remote\RsyncCommandBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RsyncCommandBuilderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncCommandBuilderTest extends TestCase
{
    private RsyncCommandBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RsyncCommandBuilder();
    }

    #[Test]
    public function authorizationWithSshKey(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', sshKey: '/home/u/.ssh/id_rsa', port: 2222);

        self::assertSame(
            "-e 'ssh -i /home/u/.ssh/id_rsa -p2222 -o StrictHostKeyChecking=yes'",
            $this->builder->authorization($client, false),
        );
    }

    #[Test]
    public function authorizationPlainWhenNoKeyNoSshpass(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', port: 22);

        self::assertSame(
            "-e 'ssh -p22 -o StrictHostKeyChecking=yes'",
            $this->builder->authorization($client, false),
        );
    }

    #[Test]
    public function authorizationWithSshpass(): void
    {
        $client = new ClientConfig(host: 'h', user: 'deploy', password: 'secret', port: 2200);

        self::assertSame(
            "--rsh='sshpass -e ssh -p2200 -o StrictHostKeyChecking=yes -l deploy'",
            $this->builder->authorization($client, true),
        );
    }

    /**
     * The data channel has to follow the same `ssh_strict_host_key_checking` key
     * as the command channel. It used to hard-code `no` regardless.
     */
    #[Test]
    public function authorizationHonoursTheStrictHostKeyCheckingSetting(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', port: 22);

        self::assertStringContainsString(
            'StrictHostKeyChecking=yes',
            $this->builder->authorization($client, false, null, true),
        );
        self::assertStringContainsString(
            'StrictHostKeyChecking=no',
            $this->builder->authorization($client, false, null, false),
        );
    }

    #[Test]
    public function authorizationQuotesAnAwkwardKeyPath(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', sshKey: '/keys/$(id).pem', port: 22);

        self::assertSame(
            "-e 'ssh -i /keys/$(id).pem -p22 -o StrictHostKeyChecking=yes'",
            $this->builder->authorization($client, false),
        );
    }

    #[Test]
    public function passwordEnvironmentOnlyWithSshpassAndPasswordAndNoKey(): void
    {
        $withPassword = new ClientConfig(host: 'h', user: 'u', password: 'secret');
        self::assertSame('SSHPASS=secret ', $this->builder->passwordEnvironment($withPassword, true));
        self::assertSame('', $this->builder->passwordEnvironment($withPassword, false));

        $withKey = new ClientConfig(host: 'h', user: 'u', password: 'secret', sshKey: '/k');
        self::assertSame('', $this->builder->passwordEnvironment($withKey, true));
    }

    #[Test]
    public function passwordEnvironmentQuotesAPasswordWithShellCharacters(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', password: "p'a\$ss");

        self::assertSame(
            'SSHPASS=\'p\'"\'"\'a$ss\' ',
            $this->builder->passwordEnvironment($client, true),
        );
    }

    #[Test]
    public function aSingleDumpFileSkipsTheDirectoryOnlyOptions(): void
    {
        $directory = $this->builder->options(null);
        $singleFile = $this->builder->options(null, [], false, true);

        self::assertStringContainsString('-z', $directory);
        self::assertStringContainsString('--delete', $directory);
        self::assertStringContainsString('--iconv=UTF-8', $directory);

        self::assertStringNotContainsString('-z', $singleFile);
        self::assertStringNotContainsString('--delete', $singleFile);
        self::assertStringNotContainsString('--iconv', $singleFile);
        self::assertStringContainsString('--chmod=F660', $singleFile);
    }

    #[Test]
    public function userHostOnlyForRemote(): void
    {
        self::assertSame('deploy@server', $this->builder->userHost(new ClientConfig(host: 'server', user: 'deploy')));
        self::assertSame('', $this->builder->userHost(new ClientConfig(path: '/var/www')));
    }

    #[Test]
    public function optionsAppendAdditionalWithoutSeparator(): void
    {
        self::assertSame(
            '--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660',
            $this->builder->options(null),
        );
        self::assertSame(
            '--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 --progress',
            $this->builder->options(' --progress'),
        );
    }

    #[Test]
    public function optionsRequestMachineReadableProgressWhenAskedFor(): void
    {
        self::assertSame(
            '--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 --info=progress2 --no-i-r',
            $this->builder->options(null, withProgress: true),
        );
    }

    #[Test]
    public function optionsInsertsASeparatingSpaceEvenWithoutOne(): void
    {
        self::assertSame(
            '--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 -z',
            $this->builder->options('-z'),
        );
    }

    #[Test]
    public function optionsAppendsExcludePatternsBeforeAdditional(): void
    {
        self::assertSame(
            "--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 --exclude='*.log' --exclude='cache/' --progress",
            $this->builder->options(' --progress', ['*.log', 'cache/']),
        );
    }

    #[Test]
    public function optionsWithExcludePatternsAndNoAdditional(): void
    {
        self::assertSame(
            "--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 --exclude='*.log'",
            $this->builder->options(null, ['*.log']),
        );
    }

    #[Test]
    public function optionsEscapesSingleQuotesInExcludePatterns(): void
    {
        self::assertSame(
            "--delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 --exclude='it'\\''s/*'",
            $this->builder->options(null, ["it's/*"]),
        );
    }

    #[Test]
    public function authorizationIncludesProxyJumpWhenJumpHostSet(): void
    {
        $auth = (new RsyncCommandBuilder())->authorization(
            new ClientConfig(host: 'db.internal', user: 'app', sshKey: '/keys/id', port: 2222),
            false,
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump', port: 2200),
        );

        self::assertStringContainsString('-J jump@bastion.example.com:2200', $auth);
        self::assertStringContainsString('ssh', $auth);
    }

    #[Test]
    public function authorizationOmitsProxyJumpWhenNoJumpHost(): void
    {
        $auth = (new RsyncCommandBuilder())->authorization(
            new ClientConfig(host: 'db.internal', user: 'app', port: 22),
            false,
        );

        self::assertStringNotContainsString('-J ', $auth);
    }

    #[Test]
    public function buildAssemblesReceiverCommand(): void
    {
        $command = $this->builder->build(
            '',
            $this->builder->options(null),
            '-e "ssh -p22 -o StrictHostKeyChecking=no"',
            'deploy@server',
            '/var/www/fileadmin/',
            '',
            '/local/fileadmin/',
        );

        self::assertSame(
            'rsync --delete -a -z --stats --human-readable --iconv=UTF-8 --chmod=D2770,F660 '
            .'-e "ssh -p22 -o StrictHostKeyChecking=no" deploy@server:/var/www/fileadmin/ /local/fileadmin/',
            $command,
        );
    }
}
