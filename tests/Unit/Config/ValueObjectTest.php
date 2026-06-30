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

namespace MoveElevator\DbSyncTool\Tests\Unit\Config;

use MoveElevator\DbSyncTool\Config\{ClientConfig, DatabaseConfig, JumpHostConfig};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * ValueObjectTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class ValueObjectTest extends TestCase
{
    #[Test]
    public function databaseConfigFromFullArray(): void
    {
        $config = DatabaseConfig::fromArray([
            'name' => 'mydb',
            'host' => 'localhost',
            'port' => '3306',
            'user' => 'admin',
            'password' => 'secret',
        ]);

        self::assertSame('mydb', $config->name);
        self::assertSame('localhost', $config->host);
        self::assertSame(3306, $config->port);
        self::assertSame('admin', $config->user);
        self::assertSame('secret', $config->password);
    }

    #[Test]
    public function databaseConfigDefaults(): void
    {
        $config = DatabaseConfig::fromArray(['name' => 'testdb']);

        self::assertSame('testdb', $config->name);
        self::assertSame('', $config->host);
        self::assertSame(0, $config->port);

        self::assertSame('', DatabaseConfig::fromArray(null)->name);
        self::assertSame('', DatabaseConfig::fromArray([])->name);
    }

    #[Test]
    public function jumpHostConfigFromFullArray(): void
    {
        $config = JumpHostConfig::fromArray([
            'host' => 'jump.example.com',
            'user' => 'jumpuser',
            'port' => '2222',
            'password' => 'secret',
        ]);

        self::assertNotNull($config);
        self::assertSame('jump.example.com', $config->host);
        self::assertSame('jumpuser', $config->user);
        self::assertSame(2222, $config->port);
        self::assertSame('secret', $config->password);
    }

    #[Test]
    public function jumpHostConfigReturnsNullForEmptyOrNull(): void
    {
        self::assertNull(JumpHostConfig::fromArray(null));
        self::assertNull(JumpHostConfig::fromArray([]));
    }

    #[Test]
    public function jumpHostConfigWithoutHost(): void
    {
        $config = JumpHostConfig::fromArray(['user' => 'admin']);

        self::assertNotNull($config);
        self::assertSame('', $config->host);
        self::assertSame('admin', $config->user);
    }

    #[Test]
    public function clientConfigLocalAndRemote(): void
    {
        $local = ClientConfig::fromArray(['path' => '/var/www/config.php']);
        self::assertSame('/var/www/config.php', $local->path);
        self::assertSame('', $local->host);
        self::assertFalse($local->isRemote());

        $remote = ClientConfig::fromArray([
            'host' => 'server.example.com',
            'user' => 'admin',
            'password' => 'secret',
            'path' => '/var/www/config.php',
        ]);
        self::assertSame('server.example.com', $remote->host);
        self::assertSame('admin', $remote->user);
        self::assertTrue($remote->isRemote());
    }

    #[Test]
    public function clientConfigParsesNestedDbAndJumpHost(): void
    {
        $config = ClientConfig::fromArray([
            'host' => 'target.internal',
            'user' => 'admin',
            'db' => ['name' => 'mydb', 'user' => 'dbuser'],
            'jump_host' => ['host' => 'jump.example.com', 'user' => 'jumpuser'],
        ]);

        self::assertSame('mydb', $config->db->name);
        self::assertSame('dbuser', $config->db->user);
        self::assertNotNull($config->jumpHost);
        self::assertSame('jump.example.com', $config->jumpHost->host);
    }

    #[Test]
    public function clientConfigDefaults(): void
    {
        $config = ClientConfig::fromArray(null);

        self::assertSame('', $config->path);
        self::assertSame('', $config->host);
        self::assertSame(22, $config->port);
        self::assertSame('/tmp/', $config->dumpDir);
        self::assertNull($config->keepDumps);
        self::assertFalse($config->protect);
    }

    #[Test]
    public function databaseConfigReadsSslDisabled(): void
    {
        self::assertTrue(DatabaseConfig::fromArray(['name' => 'db', 'ssl_disabled' => true])->sslDisabled);
        self::assertFalse(DatabaseConfig::fromArray(['name' => 'db'])->sslDisabled);
        self::assertFalse((new DatabaseConfig())->sslDisabled);
    }
}
