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

namespace KonradMichalik\SyncTool\Tests\Unit\Util;

use KonradMichalik\SyncTool\Util\Pure;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * PureTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class PureTest extends TestCase
{
    #[Test]
    #[DataProvider('versionProvider')]
    public function parseVersionExtractsFirstToken(?string $input, ?string $expected): void
    {
        self::assertSame($expected, Pure::parseVersion($input));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function versionProvider(): iterable
    {
        yield 'rsync' => ['rsync  version 3.2.7  protocol version 31', '3.2.7'];
        yield 'sshpass' => ['sshpass 1.10', '1.10'];
        yield 'mysql' => ['mysql  Ver 8.0.32 for Linux on x86_64', '8.0.32'];
        yield 'mariadb' => ['mariadb from 11.4.2-MariaDB, client 15.2', '11.4.2'];
        yield 'generic' => ['Version 1.0', '1.0'];
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'no digits' => ['no version here', null];
    }

    #[Test]
    #[DataProvider('quotesProvider')]
    public function removeSurroundingQuotesStripsOnlyOuterPair(mixed $input, mixed $expected): void
    {
        self::assertSame($expected, Pure::removeSurroundingQuotes($input));
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function quotesProvider(): iterable
    {
        yield 'double' => ['"hello"', 'hello'];
        yield 'single' => ["'world'", 'world'];
        yield 'plain' => ['plain', 'plain'];
        yield 'mixed quotes unchanged' => ['\'mixed"', '\'mixed"'];
        yield 'only start' => ['"start', '"start'];
        yield 'only end' => ['end"', 'end"'];
        yield 'empty double' => ['""', ''];
        yield 'empty single' => ["''", ''];
        yield 'int unchanged' => [123, 123];
        yield 'null unchanged' => [null, null];
        yield 'inner preserved' => ['\'"inner"\'', '"inner"'];
    }

    #[Test]
    public function cleanDbConfigStripsValueQuotes(): void
    {
        $result = Pure::cleanDbConfig([
            'user' => '"admin"',
            'password' => "'secret'",
            'host' => 'localhost',
            'port' => 3306,
            'ssl' => true,
        ]);

        self::assertSame(
            ['user' => 'admin', 'password' => 'secret', 'host' => 'localhost', 'port' => 3306, 'ssl' => true],
            $result,
        );
        self::assertSame([], Pure::cleanDbConfig([]));
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string>|null    $expected
     */
    #[Test]
    #[DataProvider('dictToArgsProvider')]
    public function dictToArgsMapsBooleansAndValues(array $input, ?array $expected): void
    {
        self::assertSame($expected, Pure::dictToArgs($input));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>|null}>
     */
    public static function dictToArgsProvider(): iterable
    {
        yield 'flag true' => [['verbose' => true], ['--verbose']];
        yield 'flag false' => [['verbose' => false], null];
        yield 'value' => [['output' => 'file.txt'], ['--output', 'file.txt']];
        yield 'int value' => [['port' => 3306], ['--port', '3306']];
        yield 'null skipped' => [['config' => null], null];
        yield 'mixed' => [
            ['verbose' => true, 'quiet' => false, 'output' => 'dump.sql', 'port' => 3306, 'config' => null],
            ['--verbose', '--output', 'dump.sql', '--port', '3306'],
        ];
        yield 'empty' => [[], null];
        yield 'all false' => [['a' => false, 'b' => false], null];
    }

    #[Test]
    public function removeMultipleElementsFromStringRemovesSequentially(): void
    {
        self::assertSame('', Pure::removeMultipleElementsFromString(['a'], ''));
        self::assertSame('', Pure::removeMultipleElementsFromString(['foo', 'bar'], 'foobarfoo'));
    }

    #[Test]
    #[DataProvider('pathProvider')]
    public function getFileFromPathReturnsBasename(string $path, string $expected): void
    {
        self::assertSame($expected, Pure::getFileFromPath($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'nested file' => ['/var/www/html/app.config.php', 'app.config.php'];
        yield 'trailing slash' => ['/var/www/', 'www'];
        yield 'simple' => ['/home/user/documents/file.txt', 'file.txt'];
    }

    /**
     * A bare `array_filter()` treats `"0"` as falsy and drops it along with the
     * empty lines it is meant to remove, a table or row literally named `0`
     * would silently disappear from a listing.
     */
    #[Test]
    public function outputLinesKeepsALineThatIsLiterallyZero(): void
    {
        self::assertSame(['a', '0', 'b'], Pure::outputLines("a\n0\n\nb\n"));
    }
}
