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
use KonradMichalik\SyncTool\Remote\{LocalCommandRunner, RunnerFactory, SystemSshCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RunnerFactoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RunnerFactoryTest extends TestCase
{
    #[Test]
    public function localClientYieldsLocalRunner(): void
    {
        $factory = new RunnerFactory();

        self::assertInstanceOf(LocalCommandRunner::class, $factory->local());
        self::assertInstanceOf(LocalCommandRunner::class, $factory->forClient(new ClientConfig(path: '/var/www')));
    }

    #[Test]
    public function remoteClientWithJumpHostYieldsSystemSshRunner(): void
    {
        $client = new ClientConfig(
            host: 'remote.example.com',
            user: 'deploy',
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'proxy'),
        );

        self::assertInstanceOf(SystemSshCommandRunner::class, (new RunnerFactory())->forClient($client));
    }

    #[Test]
    public function connectingToAJumpHostedClientIsLogged(): void
    {
        $logs = [];
        $factory = new RunnerFactory(log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });
        $client = new ClientConfig(
            host: 'remote.example.com',
            user: 'deploy',
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'proxy'),
        );

        $factory->forClient($client);

        self::assertContains('Connecting via SSH to deploy@remote.example.com (via jump host jump.example.com)', $logs);
    }

    #[Test]
    public function reusingACachedConnectionIsLoggedOnlyOnce(): void
    {
        $logs = [];
        $factory = new RunnerFactory(log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        });
        $client = new ClientConfig(
            host: 'remote.example.com',
            user: 'deploy',
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'proxy'),
        );

        $factory->forClient($client);
        $factory->forClient($client);

        self::assertCount(1, $logs);
    }

    /**
     * A run asks for the same endpoint in several phases. Handing out a fresh
     * runner each time meant a fresh SSH handshake each time.
     */
    #[Test]
    public function theSameEndpointIsServedByOneRunner(): void
    {
        $factory = new RunnerFactory();
        $client = new ClientConfig(
            host: 'remote.example.com',
            user: 'deploy',
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'proxy'),
        );

        self::assertSame($factory->forClient($client), $factory->forClient($client));
    }

    #[Test]
    public function endpointsThatDifferGetTheirOwnRunner(): void
    {
        $factory = new RunnerFactory();
        $jump = new JumpHostConfig(host: 'jump.example.com', user: 'proxy');

        $first = $factory->forClient(new ClientConfig(host: 'a.example.com', user: 'deploy', jumpHost: $jump));
        $second = $factory->forClient(new ClientConfig(host: 'b.example.com', user: 'deploy', jumpHost: $jump));
        $otherUser = $factory->forClient(new ClientConfig(host: 'a.example.com', user: 'other', jumpHost: $jump));
        $otherPort = $factory->forClient(new ClientConfig(host: 'a.example.com', user: 'deploy', port: 2222, jumpHost: $jump));

        self::assertNotSame($first, $second, 'a different host is a different connection');
        self::assertNotSame($first, $otherUser, 'a different user is a different connection');
        self::assertNotSame($first, $otherPort, 'a different port is a different connection');
    }

    #[Test]
    public function relaxingHostKeyCheckingDoesNotReuseAStrictConnection(): void
    {
        $factory = new RunnerFactory();
        $client = new ClientConfig(
            host: 'remote.example.com',
            user: 'deploy',
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'proxy'),
        );

        self::assertNotSame(
            $factory->forClient($client, strictHostKeyChecking: true),
            $factory->forClient($client, strictHostKeyChecking: false),
        );
    }
}
