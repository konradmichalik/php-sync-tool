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

namespace KonradMichalik\SyncTool\Tests\Unit\Command;

use KonradMichalik\SyncTool\Command\EndpointOverrides;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EndpointOverridesTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class EndpointOverridesTest extends TestCase
{
    #[Test]
    public function emptyRawProducesEmptyArray(): void
    {
        self::assertSame([], EndpointOverrides::build([]));
    }

    #[Test]
    public function mapsTopLevelSuffixesToConfigKeys(): void
    {
        self::assertSame(
            ['host' => 'h', 'user' => 'u', 'ssh_key' => '/k', 'dump_dir' => '/d', 'keep_dumps' => '3'],
            EndpointOverrides::build([
                'host' => 'h', 'user' => 'u', 'key' => '/k', 'dump-dir' => '/d', 'keep-dumps' => '3',
            ]),
        );
    }

    #[Test]
    public function nestsDatabaseSuffixesUnderDb(): void
    {
        self::assertSame(
            ['db' => ['name' => 'app', 'port' => '3307']],
            EndpointOverrides::build(['db-name' => 'app', 'db-port' => '3307']),
        );
    }

    #[Test]
    public function ignoresNullValues(): void
    {
        self::assertSame(
            ['path' => '/var/www'],
            EndpointOverrides::build(['path' => '/var/www', 'host' => null, 'db-name' => null]),
        );
    }
}
