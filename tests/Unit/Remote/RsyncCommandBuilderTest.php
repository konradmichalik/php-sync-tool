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
use MoveElevator\DbSyncTool\Remote\RsyncCommandBuilder;
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

        self::assertSame('-e "ssh -i /home/u/.ssh/id_rsa -p2222"', $this->builder->authorization($client, false));
    }

    #[Test]
    public function authorizationPlainWhenNoKeyNoSshpass(): void
    {
        $client = new ClientConfig(host: 'h', user: 'u', port: 22);

        self::assertSame('-e "ssh -p22 -o StrictHostKeyChecking=no"', $this->builder->authorization($client, false));
    }

    #[Test]
    public function authorizationWithSshpass(): void
    {
        $client = new ClientConfig(host: 'h', user: 'deploy', password: 'secret', port: 2200);

        self::assertSame(
            '--rsh="sshpass -e ssh -p2200 -o StrictHostKeyChecking=no -l deploy"',
            $this->builder->authorization($client, true),
        );
    }

    #[Test]
    public function passwordEnvironmentOnlyWithSshpassAndPasswordAndNoKey(): void
    {
        $withPassword = new ClientConfig(host: 'h', user: 'u', password: 'secret');
        self::assertSame("SSHPASS='secret' ", $this->builder->passwordEnvironment($withPassword, true));
        self::assertSame('', $this->builder->passwordEnvironment($withPassword, false));

        $withKey = new ClientConfig(host: 'h', user: 'u', password: 'secret', sshKey: '/k');
        self::assertSame('', $this->builder->passwordEnvironment($withKey, true));
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
