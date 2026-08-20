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
}
