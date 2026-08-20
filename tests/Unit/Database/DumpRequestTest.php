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

namespace KonradMichalik\SyncTool\Tests\Unit\Database;

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\DumpRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DumpRequestTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DumpRequestTest extends TestCase
{
    #[Test]
    public function carriesEverythingADumpNeedsAndDefaultsTheOptionalParts(): void
    {
        $db = new DatabaseConfig(name: 'app');
        $request = new DumpRequest($db, '/tmp/.my_ab12.cnf', '/tmp/app.sql');

        self::assertSame($db, $request->db);
        self::assertSame('/tmp/.my_ab12.cnf', $request->credentialsPath);
        self::assertSame('/tmp/app.sql', $request->dumpFilePath);
        self::assertSame([], $request->exportTables);
        self::assertSame([], $request->ignoreTables);
        self::assertSame('', $request->where);
        self::assertSame('', $request->additionalOptions);
    }

    #[Test]
    public function carriesTablePartitioningAndExtraOptions(): void
    {
        $request = new DumpRequest(
            new DatabaseConfig(name: 'app'),
            '/tmp/.my_ab12.cnf',
            '/tmp/app.sql',
            exportTables: ['users'],
            ignoreTables: ['cache_pages'],
            where: 'id > 1',
            additionalOptions: '--skip-lock-tables',
        );

        self::assertSame(['users'], $request->exportTables);
        self::assertSame(['cache_pages'], $request->ignoreTables);
        self::assertSame('id > 1', $request->where);
        self::assertSame('--skip-lock-tables', $request->additionalOptions);
    }
}
