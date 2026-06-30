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

namespace MoveElevator\DbSyncTool\Tests\Unit\Enum;

use MoveElevator\DbSyncTool\Enum\Framework;
use MoveElevator\DbSyncTool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;


/**
 * FrameworkTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class FrameworkTest extends TestCase
{
    #[Test]
    #[DataProvider('caseInsensitiveProvider')]
    public function fromStringIsCaseInsensitive(string $input, Framework $expected): void
    {
        self::assertSame($expected, Framework::fromString($input));
    }

    /**
     * @return iterable<string, array{string, Framework}>
     */
    public static function caseInsensitiveProvider(): iterable
    {
        yield 'exact' => ['TYPO3', Framework::Typo3];
        yield 'lower' => ['symfony', Framework::Symfony];
        yield 'upper' => ['DRUPAL', Framework::Drupal];
        yield 'mixed' => ['WordPress', Framework::WordPress];
        yield 'laravel lower' => ['laravel', Framework::Laravel];
    }

    #[Test]
    public function unknownFrameworkThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unknown framework type: Joomla');

        Framework::fromString('Joomla');
    }
}
