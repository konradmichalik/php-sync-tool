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

use KonradMichalik\SyncTool\Security\Shell;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * ShellTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ShellTest extends TestCase
{
    #[Test]
    #[DataProvider('quotingProvider')]
    public function quoteProducesShlexCompatibleOutput(int|string|null $input, string $expected): void
    {
        self::assertSame($expected, Shell::quote($input));
    }

    /**
     * @return iterable<string, array{int|string|null, string}>
     */
    public static function quotingProvider(): iterable
    {
        yield 'simple string needs no quoting' => ['hello', 'hello'];
        yield 'spaces are quoted' => ['hello world', "'hello world'"];
        yield 'empty string' => ['', "''"];
        yield 'null becomes empty quoted' => [null, "''"];
        yield 'single quotes are escaped' => ["it's a test", "'it'\"'\"'s a test'"];
        yield 'double quotes' => ['say "hello"', '\'say "hello"\''];
        yield 'backticks' => ['`whoami`', "'`whoami`'"];
        yield 'dollar expansion' => ['$HOME', "'\$HOME'"];
        yield 'semicolon injection' => ['test; rm -rf /', "'test; rm -rf /'"];
        yield 'pipe injection' => ['test | cat /etc/passwd', "'test | cat /etc/passwd'"];
        yield 'ampersand injection' => ['test & malicious', "'test & malicious'"];
        yield 'newline injection' => ["test\nmalicious", "'test\nmalicious'"];
        yield 'null byte injection' => ["test\x00malicious", "'test\x00malicious'"];
        yield 'integer is stringified without quoting' => [123, '123'];
    }

    #[Test]
    public function pathTraversalIsPreservedSafely(): void
    {
        self::assertStringContainsString('../../../etc/passwd', Shell::quote('../../../etc/passwd'));
    }

    #[Test]
    public function specialCharactersArePreservedInsideQuotes(): void
    {
        $special = '!@#$%^&*()[]{}|\\:;<>?/';

        self::assertStringContainsString($special, Shell::quote($special));
    }
}
