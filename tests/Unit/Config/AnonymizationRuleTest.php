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
        $this->expectExceptionMessageMatches('#fake:name#');

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

    #[Test]
    public function aPresetExpandsToItsCannedRules(): void
    {
        $rules = AnonymizationRule::fromConfig(['preset' => 'typo3']);

        $targets = array_map(static fn (AnonymizationRule $rule): string => $rule->table.'.'.$rule->column, $rules);
        self::assertContains('fe_users.email', $targets);
        self::assertContains('be_users.email', $targets);
        self::assertNotContains('fe_users.username', $targets, 'a synced dev copy must stay usable, so the login identifier is left untouched');
        self::assertNotContains('fe_users.password', $targets, 'rehashing an already one-way hash breaks TYPO3 login without any privacy upside');
        self::assertNotContains('be_users.username', $targets, 'a synced dev copy must stay usable, so the login identifier is left untouched');
        self::assertNotContains('be_users.password', $targets, 'rehashing an already one-way hash breaks TYPO3 login without any privacy upside');
    }

    #[Test]
    public function explicitColumnsOverrideAMatchingPresetColumn(): void
    {
        $rules = AnonymizationRule::fromConfig([
            'preset' => 'typo3',
            'fe_users' => ['email' => 'null'],
        ]);

        $email = self::ruleFor($rules, 'fe_users', 'email');
        self::assertNotNull($email);
        self::assertSame(AnonymizationStrategy::Nullify, $email->strategy, 'the explicit rule wins over the preset default');
    }

    #[Test]
    public function explicitColumnsExtendAPresetTableWithoutRemovingItsOtherColumns(): void
    {
        $rules = AnonymizationRule::fromConfig([
            'preset' => 'typo3',
            'fe_users' => ['telephone' => 'null'],
        ]);

        self::assertNotNull(self::ruleFor($rules, 'fe_users', 'telephone'));
        self::assertNotNull(self::ruleFor($rules, 'fe_users', 'email'), 'the preset column survives alongside the added one');
    }

    #[Test]
    public function explicitTablesOutsideThePresetAreKeptUnchanged(): void
    {
        $rules = AnonymizationRule::fromConfig([
            'preset' => 'typo3',
            'sys_log' => ['details' => 'null'],
        ]);

        self::assertNotNull(self::ruleFor($rules, 'sys_log', 'details'));
        self::assertNotNull(self::ruleFor($rules, 'fe_users', 'email'), 'the preset itself is still applied');
    }

    #[Test]
    public function rejectsAnUnknownPreset(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('#bogus#');

        AnonymizationRule::fromConfig(['preset' => 'bogus']);
    }

    /**
     * @param list<AnonymizationRule> $rules
     */
    private static function ruleFor(array $rules, string $table, string $column): ?AnonymizationRule
    {
        foreach ($rules as $rule) {
            if ($rule->table === $table && $rule->column === $column) {
                return $rule;
            }
        }

        return null;
    }
}
