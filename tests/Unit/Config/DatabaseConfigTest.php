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

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseConfigTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DatabaseConfigTest extends TestCase
{
    #[Test]
    public function defaultsToMysqlWhenNoTypeIsConfigured(): void
    {
        self::assertSame(DatabaseSystem::MySQL, DatabaseConfig::fromArray(['name' => 'app'])->type);
    }

    #[Test]
    public function readsTheConfiguredDatabaseType(): void
    {
        self::assertSame(
            DatabaseSystem::PostgreSQL,
            DatabaseConfig::fromArray(['name' => 'app', 'type' => 'postgres'])->type,
        );
    }

    #[Test]
    public function fallsBackToMysqlForAnUnknownType(): void
    {
        self::assertSame(
            DatabaseSystem::MySQL,
            DatabaseConfig::fromArray(['name' => 'app', 'type' => 'oracle'])->type,
        );
    }

    #[Test]
    public function everyExplicitlyConfiguredValueWinsOverTheDetectedOne(): void
    {
        $detected = new DatabaseConfig('detected_db', 'postgres', 'detected_user', 'detected_pw', 5432);
        $explicit = DatabaseConfig::fromArray([
            'name' => 'other_db',
            'host' => '127.0.0.1',
            'user' => 'other_user',
            'password' => 'other_pw',
            'port' => 6543,
        ]);

        $merged = $detected->overriddenBy($explicit);

        self::assertSame('other_db', $merged->name);
        self::assertSame('127.0.0.1', $merged->host);
        self::assertSame('other_user', $merged->user);
        self::assertSame('other_pw', $merged->password);
        self::assertSame(6543, $merged->port);
    }

    #[Test]
    public function unconfiguredValuesFallBackToTheDetectedOnes(): void
    {
        $detected = new DatabaseConfig('detected_db', 'postgres', 'detected_user', 'detected_pw', 5432);

        $merged = $detected->overriddenBy(DatabaseConfig::fromArray(['host' => '127.0.0.1']));

        self::assertSame('127.0.0.1', $merged->host, 'the one configured value is applied');
        self::assertSame('detected_db', $merged->name);
        self::assertSame('detected_user', $merged->user);
        self::assertSame('detected_pw', $merged->password);
        self::assertSame(5432, $merged->port);
    }

    #[Test]
    public function theDetectedDatabaseTypeStaysAuthoritative(): void
    {
        $detected = new DatabaseConfig('app', 'postgres', 'u', 'p', 5432, type: DatabaseSystem::PostgreSQL);

        // An unset type is indistinguishable from an explicit "mysql", so a
        // configured type must not silently downgrade the detected driver.
        $merged = $detected->overriddenBy(DatabaseConfig::fromArray(['host' => '127.0.0.1']));

        self::assertSame(DatabaseSystem::PostgreSQL, $merged->type);
    }

    #[Test]
    public function tlsSettingsComeFromTheConfigurationNotFromDetection(): void
    {
        $detected = new DatabaseConfig('app', 'db', 'u', 'p', 3306);

        $merged = $detected->overriddenBy(DatabaseConfig::fromArray(['ssl_skip_verify' => true, 'ssl_ca' => '/ca.pem']));

        self::assertTrue($merged->sslSkipVerify);
        self::assertSame('/ca.pem', $merged->sslCa);
    }
}
