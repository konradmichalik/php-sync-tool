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

namespace KonradMichalik\SyncTool\Tests\Unit\Config;

use KonradMichalik\SyncTool\Config\AnonymizationRule;
use KonradMichalik\SyncTool\Enum\AnonymizationStrategy;
use KonradMichalik\SyncTool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AnonymizationRuleTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class AnonymizationRuleTest extends TestCase
{
    #[Test]
    public function noBlockMeansNoRules(): void
    {
        self::assertSame([], AnonymizationRule::fromConfig(null));
        self::assertSame([], AnonymizationRule::fromConfig([]));
    }

    #[Test]
    public function readsTheShorthandNotation(): void
    {
        $rules = AnonymizationRule::fromConfig(['fe_users' => ['email' => 'email']]);

        self::assertCount(1, $rules);
        self::assertSame('fe_users', $rules[0]->table);
        self::assertSame('email', $rules[0]->column);
        self::assertSame(AnonymizationStrategy::Email, $rules[0]->strategy);
        self::assertNull($rules[0]->value);
    }

    #[Test]
    public function readsTheObjectNotationWithItsValue(): void
    {
        $rules = AnonymizationRule::fromConfig([
            'fe_users' => ['name' => ['strategy' => 'static', 'value' => 'Redacted']],
        ]);

        self::assertCount(1, $rules);
        self::assertSame(AnonymizationStrategy::StaticValue, $rules[0]->strategy);
        self::assertSame('Redacted', $rules[0]->value);
    }

    #[Test]
    public function keepsEveryTableAndColumnInConfigurationOrder(): void
    {
        $rules = AnonymizationRule::fromConfig([
            'fe_users' => ['email' => 'email', 'password' => 'hash'],
            'sys_log' => ['details' => 'null'],
        ]);

        self::assertSame(
            ['fe_users.email', 'fe_users.password', 'sys_log.details'],
            array_map(static fn (AnonymizationRule $rule): string => $rule->table.'.'.$rule->column, $rules),
        );
    }

    #[Test]
    public function rejectsAnUnknownStrategyAndNamesTheColumn(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('#fe_users\.email#');

        AnonymizationRule::fromConfig(['fe_users' => ['email' => 'shuffle']]);
    }

    #[Test]
    public function rejectsAStaticRuleWithoutAValue(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('#value#');

        AnonymizationRule::fromConfig(['fe_users' => ['name' => ['strategy' => 'static']]]);
    }

    #[Test]
    public function rejectsAnUnquotedYamlNullBecauseItReadsAsNoStrategy(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('#fe_users\.password#');

        AnonymizationRule::fromConfig(['fe_users' => ['password' => null]]);
    }
}
