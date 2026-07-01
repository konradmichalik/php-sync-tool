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
}
