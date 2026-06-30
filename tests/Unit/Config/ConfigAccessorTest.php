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

use MoveElevator\DbSyncTool\Config\ConfigAccessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ConfigAccessorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigAccessorTest extends TestCase
{
    #[Test]
    public function getReturnsValueWhenPresent(): void
    {
        self::assertSame('value', ConfigAccessor::get(['key' => 'value'], 'key', 'default'));
    }

    #[Test]
    public function getReturnsDefaultWhenMissing(): void
    {
        self::assertSame('default', ConfigAccessor::get([], 'key', 'default'));
    }

    #[Test]
    public function getTreatsNullAsMissing(): void
    {
        self::assertSame('default', ConfigAccessor::get(['key' => null], 'key', 'default'));
    }

    #[Test]
    public function getKeepsFalsyButNonNullValues(): void
    {
        self::assertSame('', ConfigAccessor::get(['key' => ''], 'key', 'default'));
        self::assertFalse(ConfigAccessor::get(['key' => false], 'key', true));
        self::assertSame(0, ConfigAccessor::get(['key' => 0], 'key', 42));
    }

    #[Test]
    public function getIntConvertsAndFallsBack(): void
    {
        self::assertSame(3306, ConfigAccessor::getInt(['port' => 3306], 'port', 22));
        self::assertSame(3306, ConfigAccessor::getInt(['port' => '3306'], 'port', 22));
        self::assertSame(22, ConfigAccessor::getInt([], 'port', 22));
        self::assertSame(22, ConfigAccessor::getInt(['port' => 'invalid'], 'port', 22));
        self::assertSame(22, ConfigAccessor::getInt(['port' => null], 'port', 22));
        self::assertSame(22, ConfigAccessor::getInt(['port' => '3306.5'], 'port', 22));
    }

    #[Test]
    public function getListReturnsListsWithFallback(): void
    {
        self::assertSame([1, 2, 3], ConfigAccessor::getList(['items' => [1, 2, 3]], 'items'));
        self::assertSame([], ConfigAccessor::getList([], 'items'));
        self::assertSame([], ConfigAccessor::getList(['items' => null], 'items'));
        self::assertSame(['users'], ConfigAccessor::getList(['ignore_table' => ['users']], 'ignore_tables', 'ignore_table'));
        self::assertSame(['a'], ConfigAccessor::getList(['ignore_tables' => ['a'], 'ignore_table' => ['b']], 'ignore_tables', 'ignore_table'));
    }
}
