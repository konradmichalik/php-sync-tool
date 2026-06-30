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

use KonradMichalik\SyncTool\Database\TableStatements;
use KonradMichalik\SyncTool\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TableStatementsTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class TableStatementsTest extends TestCase
{
    private TableStatements $tables;

    protected function setUp(): void
    {
        $this->tables = new TableStatements();
    }

    #[Test]
    public function exportTablesSplitsTrimsAndValidates(): void
    {
        self::assertSame(['users', 'orders', 'cache_pages'], $this->tables->exportTables('users, orders ,cache_pages'));
        self::assertSame([], $this->tables->exportTables(''));
    }

    #[Test]
    public function exportTablesRejectsInjection(): void
    {
        $this->expectException(ValidationException::class);

        $this->tables->exportTables('users; DROP TABLE users');
    }

    #[Test]
    public function ignoreTableOptionStripsBackticks(): void
    {
        self::assertSame('--ignore-table=mydb.cache', $this->tables->ignoreTableOption('mydb', 'cache'));
    }

    #[Test]
    public function truncateAndDropStatementsAreBacktickQuoted(): void
    {
        self::assertSame('TRUNCATE TABLE `sessions`', $this->tables->truncateStatement('sessions'));
        self::assertSame('DROP TABLE `users`', $this->tables->dropStatement('users'));
    }

    #[Test]
    public function foreignKeyBatchWrapsStatements(): void
    {
        $batch = $this->tables->foreignKeyDisabledBatch([
            'TRUNCATE TABLE `a`',
            'TRUNCATE TABLE `b`',
        ]);

        self::assertSame(
            'SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE `a`; TRUNCATE TABLE `b`; SET FOREIGN_KEY_CHECKS = 1;',
            $batch,
        );
    }

    #[Test]
    public function foreignKeyBatchReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->tables->foreignKeyDisabledBatch([]));
    }
}
