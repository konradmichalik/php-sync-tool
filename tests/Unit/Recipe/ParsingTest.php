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

namespace KonradMichalik\SyncTool\Tests\Unit\Recipe;

use KonradMichalik\SyncTool\Exception\ParsingException;
use KonradMichalik\SyncTool\Recipe\Parsing;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * ParsingTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ParsingTest extends TestCase
{
    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[DataProvider('symfonyUrlProvider')]
    public function parseSymfonyDatabaseUrl(string $url, array $expected): void
    {
        self::assertSame($expected, Parsing::parseSymfonyDatabaseUrl($url));
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function symfonyUrlProvider(): iterable
    {
        yield 'mysql' => ['mysql://db_user:db_password@db_host:3306/db_name', [
            'db_type' => 'mysql', 'user' => 'db_user', 'password' => 'db_password',
            'host' => 'db_host', 'port' => '3306', 'name' => 'db_name',
        ]];
        yield 'mariadb' => ['mariadb://admin:secret@localhost:3307/myapp', [
            'db_type' => 'mariadb', 'user' => 'admin', 'password' => 'secret',
            'host' => 'localhost', 'port' => '3307', 'name' => 'myapp',
        ]];
        yield 'postgresql' => ['postgresql://pguser:pgpass@pghost:5432/pgdb', [
            'db_type' => 'postgresql', 'user' => 'pguser', 'password' => 'pgpass',
            'host' => 'pghost', 'port' => '5432', 'name' => 'pgdb',
        ]];
        yield 'query params stripped' => ['mysql://user:pass@host:3306/dbname?serverVersion=5.7&charset=utf8mb4', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'host', 'port' => '3306', 'name' => 'dbname',
        ]];
        yield 'numeric password' => ['mysql://user:12345678@host:3306/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => '12345678',
            'host' => 'host', 'port' => '3306', 'name' => 'db',
        ]];
        yield 'at-sign in password decoded' => ['mysql://user:p%40ssword@host:3306/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'p@ssword',
            'host' => 'host', 'port' => '3306', 'name' => 'db',
        ]];
        yield 'multi special password decoded' => ['mysql://user:P%40ss%3Aword%2Ftest@host:3306/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'P@ss:word/test',
            'host' => 'host', 'port' => '3306', 'name' => 'db',
        ]];
        yield 'trailing newline escape removed' => ["mysql://user:pass@host:3306/db\\n'", [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'host', 'port' => '3306', 'name' => 'db',
        ]];
        yield 'non-standard port' => ['mysql://user:pass@host:33060/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'host', 'port' => '33060', 'name' => 'db',
        ]];
        yield 'underscore dbname' => ['mysql://user:pass@host:3306/my_database_name', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'host', 'port' => '3306', 'name' => 'my_database_name',
        ]];
        yield 'hyphen host' => ['mysql://user:pass@db-server-01:3306/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'db-server-01', 'port' => '3306', 'name' => 'db',
        ]];
        yield 'database_url prefix stripped' => ['DATABASE_URL=mysql://user:pass@host:3306/db', [
            'db_type' => 'mysql', 'user' => 'user', 'password' => 'pass',
            'host' => 'host', 'port' => '3306', 'name' => 'db',
        ]];
    }

    #[Test]
    #[DataProvider('symfonyInvalidProvider')]
    public function parseSymfonyDatabaseUrlRejectsMalformedInput(string $url): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Mismatch');

        Parsing::parseSymfonyDatabaseUrl($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function symfonyInvalidProvider(): iterable
    {
        yield 'garbage' => ['INVALID_FORMAT'];
        yield 'empty' => [''];
        yield 'missing port' => ['mysql://user:pass@host/db'];
        yield 'missing password' => ['mysql://user@host:3306/db'];
        yield 'empty database name' => ['mysql://user:pass@host:3306/'];
    }

    #[Test]
    public function parseTypo3ThrowsWhenConnectionsHasNoDefault(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Parsing::parseTypo3DatabaseCredentials(['Connections' => ['Other' => []]]);
    }

    #[Test]
    public function parseDrupalDrushCredentialsRemapsKeys(): void
    {
        $result = Parsing::parseDrupalDrushCredentials([
            'db-name' => 'drupal_db',
            'db-hostname' => 'localhost',
            'db-password' => 'secret123',
            'db-port' => 3306,
            'db-username' => 'drupal_user',
        ]);

        self::assertSame([
            'name' => 'drupal_db', 'host' => 'localhost', 'password' => 'secret123',
            'port' => 3306, 'user' => 'drupal_user',
        ], $result);
    }

    #[Test]
    public function parseDrupalDrushCredentialsPreservesStringPort(): void
    {
        $result = Parsing::parseDrupalDrushCredentials([
            'db-name' => 'd', 'db-hostname' => 'h', 'db-password' => '',
            'db-port' => '3307', 'db-username' => 'u',
        ]);

        self::assertSame('3307', $result['port']);
        self::assertSame('', $result['password']);
    }

    #[Test]
    public function parseDrupalDrushCredentialsThrowsOnMissingKey(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Parsing::parseDrupalDrushCredentials(['db-name' => 'mydb']);
    }

    #[Test]
    public function parseTypo3V8NestedConnections(): void
    {
        $result = Parsing::parseTypo3DatabaseCredentials([
            'Connections' => ['Default' => [
                'dbname' => 'typo3_db', 'host' => 'localhost', 'password' => 'typo3pass',
                'port' => 3306, 'user' => 'typo3user',
            ]],
        ]);

        self::assertSame('typo3_db', $result['name']);
        self::assertSame('typo3_db', $result['dbname']);
        self::assertSame('localhost', $result['host']);
        self::assertSame('typo3user', $result['user']);
        self::assertSame(3306, $result['port']);
    }

    #[Test]
    public function parseTypo3V7FlatWithDefaultPort(): void
    {
        $result = Parsing::parseTypo3DatabaseCredentials([
            'database' => 'old_typo3', 'host' => 'db.server.com',
            'password' => 'oldpass', 'username' => 'olduser',
        ]);

        self::assertSame('old_typo3', $result['name']);
        self::assertSame('olduser', $result['user']);
        self::assertSame('db.server.com', $result['host']);
        self::assertSame(3306, $result['port']);
    }

    #[Test]
    public function parseTypo3PreservesExtraFieldsAndExplicitPort(): void
    {
        $result = Parsing::parseTypo3DatabaseCredentials([
            'Connections' => ['Default' => [
                'dbname' => 'db', 'user' => 'u', 'password' => 'p', 'port' => 3308,
                'charset' => 'utf8mb4', 'driver' => 'mysqli',
                'unix_socket' => '/var/run/mysqld/mysqld.sock',
            ]],
        ]);

        self::assertSame(3308, $result['port']);
        self::assertSame('utf8mb4', $result['charset']);
        self::assertSame('mysqli', $result['driver']);
        self::assertSame('/var/run/mysqld/mysqld.sock', $result['unix_socket']);
    }

    #[Test]
    public function parseTypo3PreservesUnicodePassword(): void
    {
        $result = Parsing::parseTypo3DatabaseCredentials([
            'Connections' => ['Default' => ['dbname' => 'db', 'user' => 'u', 'password' => 'pässwörd']],
        ]);

        self::assertSame('pässwörd', $result['password']);
        self::assertSame(3306, $result['port']);
    }
}
