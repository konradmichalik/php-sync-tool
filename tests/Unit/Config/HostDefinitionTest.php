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

namespace KonradMichalik\SyncTool\Tests\Unit\Config;

use KonradMichalik\SyncTool\Config\HostDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HostDefinitionTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class HostDefinitionTest extends TestCase
{
    #[Test]
    public function remoteHostReportsRemoteAndDisplaysHost(): void
    {
        $definition = HostDefinition::fromArray('live', [
            'host' => 'live.example.com',
            'user' => 'deploy',
            'path' => '/srv/app',
            'port' => '2222',
            'ssh_key' => '/home/deploy/.ssh/id_ed25519',
            'protect' => true,
            'db' => ['name' => 'app'],
        ]);

        self::assertTrue($definition->isRemote());
        self::assertSame('live (live.example.com)', $definition->displayName());
        self::assertSame(2222, $definition->port);
        self::assertTrue($definition->protect);
        self::assertSame([
            'host' => 'live.example.com',
            'user' => 'deploy',
            'path' => '/srv/app',
            'port' => 2222,
            'ssh_key' => '/home/deploy/.ssh/id_ed25519',
            'db' => ['name' => 'app'],
        ], $definition->toClientConfig());
    }

    #[Test]
    public function localHostReportsLocalAndOmitsEmptyKeys(): void
    {
        $definition = HostDefinition::fromArray('local', ['path' => '/var/www']);

        self::assertFalse($definition->isRemote());
        self::assertSame('local (local)', $definition->displayName());
        self::assertSame(['path' => '/var/www'], $definition->toClientConfig());
    }

    #[Test]
    public function carriesAnSshPasswordSoItIsNotSilentlyDropped(): void
    {
        $host = HostDefinition::fromArray('prod', [
            'host' => 'prod.example.com',
            'user' => 'deploy',
            'password' => 'secret',
        ]);

        self::assertSame('secret', $host->password);
    }

    #[Test]
    public function hasNoPasswordWhenNoneIsConfigured(): void
    {
        self::assertNull(HostDefinition::fromArray('prod', ['host' => 'prod.example.com'])->password);
    }
}
