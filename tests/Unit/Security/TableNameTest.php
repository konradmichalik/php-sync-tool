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

namespace KonradMichalik\SyncTool\Tests\Unit\Security;

use KonradMichalik\SyncTool\Exception\ValidationException;
use KonradMichalik\SyncTool\Security\TableName;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * TableNameTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class TableNameTest extends TestCase
{
    #[Test]
    #[DataProvider('validNamesProvider')]
    public function validNamesAreBacktickQuoted(string $table, string $expected): void
    {
        self::assertSame($expected, TableName::sanitize($table));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validNamesProvider(): iterable
    {
        yield 'simple' => ['users', '`users`'];
        yield 'underscore' => ['user_data', '`user_data`'];
        yield 'numbers' => ['table123', '`table123`'];
        yield 'hyphen' => ['my-table', '`my-table`'];
        yield 'dot' => ['schema.table', '`schema.table`'];
        yield 'dollar' => ['table$data', '`table$data`'];
    }

    #[Test]
    public function emptyNameThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be empty');

        TableName::sanitize('');
    }

    #[Test]
    #[DataProvider('injectionProvider')]
    public function injectionAttemptsAreRejected(string $table): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid table name');

        TableName::sanitize($table);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function injectionProvider(): iterable
    {
        yield 'semicolon drop' => ['users; DROP TABLE users;--'];
        yield 'comment' => ['users-- comment'];
        yield 'quote or' => ["users' OR '1'='1"];
        yield 'backtick' => ['users` OR `1`=`1'];
        yield 'union' => ['users UNION SELECT * FROM passwords'];
        yield 'parentheses' => ['users()'];
        yield 'newline' => ["users\nDROP TABLE users"];
        yield 'null byte' => ["users\x00malicious"];
        yield 'unicode cyrillic' => ['tаble'];
        yield 'whitespace' => ['user table'];
    }
}
