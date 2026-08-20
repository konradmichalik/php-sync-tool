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

use KonradMichalik\SyncTool\Enum\AnonymizationStrategy;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * AnonymizationStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class AnonymizationStrategyTest extends TestCase
{
    #[Test]
    #[DataProvider('configValues')]
    public function recognizesTheSpellingsUsersWriteInYaml(string $value, AnonymizationStrategy $expected): void
    {
        self::assertSame($expected, AnonymizationStrategy::fromConfigValue($value));
    }

    /**
     * @return iterable<string, array{string, AnonymizationStrategy}>
     */
    public static function configValues(): iterable
    {
        yield 'null' => ['null', AnonymizationStrategy::Nullify];
        yield 'static' => ['static', AnonymizationStrategy::StaticValue];
        yield 'hash' => ['hash', AnonymizationStrategy::Hash];
        yield 'email' => ['email', AnonymizationStrategy::Email];
        yield 'mixed case' => ['Email', AnonymizationStrategy::Email];
        yield 'padded' => [' hash ', AnonymizationStrategy::Hash];
    }

    #[Test]
    public function returnsNullForAnUnknownStrategy(): void
    {
        self::assertNull(AnonymizationStrategy::fromConfigValue('shuffle'));
    }

    #[Test]
    public function onlyTheStaticStrategyNeedsAValue(): void
    {
        self::assertTrue(AnonymizationStrategy::StaticValue->requiresValue());
        self::assertFalse(AnonymizationStrategy::Nullify->requiresValue());
        self::assertFalse(AnonymizationStrategy::Hash->requiresValue());
        self::assertFalse(AnonymizationStrategy::Email->requiresValue());
    }
}
