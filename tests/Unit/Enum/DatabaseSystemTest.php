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

namespace KonradMichalik\SyncTool\Tests\Unit\Enum;

use KonradMichalik\SyncTool\Enum\DatabaseSystem;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * DatabaseSystemTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DatabaseSystemTest extends TestCase
{
    #[Test]
    #[DataProvider('configValues')]
    public function recognizesTheSpellingsUsersWriteInYamlAndUrls(string $value, DatabaseSystem $expected): void
    {
        self::assertSame($expected, DatabaseSystem::fromConfigValue($value));
    }

    /**
     * @return iterable<string, array{string, DatabaseSystem}>
     */
    public static function configValues(): iterable
    {
        yield 'mysql' => ['mysql', DatabaseSystem::MySQL];
        yield 'mixed case' => ['MySQL', DatabaseSystem::MySQL];
        yield 'mariadb' => ['mariadb', DatabaseSystem::MariaDB];
        yield 'postgres' => ['postgres', DatabaseSystem::PostgreSQL];
        yield 'postgresql' => ['postgresql', DatabaseSystem::PostgreSQL];
        yield 'pgsql url scheme' => ['pgsql', DatabaseSystem::PostgreSQL];
        yield 'doctrine pdo scheme' => ['pdo_pgsql', DatabaseSystem::PostgreSQL];
    }

    #[Test]
    public function returnsNullForAnUnknownSystem(): void
    {
        self::assertNull(DatabaseSystem::fromConfigValue('oracle'));
    }
}
