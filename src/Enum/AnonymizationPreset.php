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

namespace KonradMichalik\SyncTool\Enum;

use function strtolower;
use function trim;

/**
 * AnonymizationPreset.
 *
 * A preset is only a shorthand for what the same project would otherwise
 * write out by hand: its rules use the same four strategies and the same
 * `anonymize` shape, merged in before the rest of the block is parsed.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum AnonymizationPreset: string
{
    case Typo3 = 'typo3';

    public static function fromConfigValue(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }

    /**
     * The columns named here are the ones TYPO3 core has shipped on
     * `fe_users`/`be_users` since long before any version this tool
     * supports. A schema that dropped or renamed one of them (or doesn't
     * have the table at all) makes the anonymize statement fail same as a
     * hand-written rule against the wrong column would: loudly, not
     * silently skipped, so nothing masked is ever mistaken for something
     * that succeeded.
     *
     * @return array<string, array<string, mixed>>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Typo3 => [
                'fe_users' => [
                    'username' => 'hash',
                    'password' => 'hash',
                    'email' => 'email',
                    'name' => ['strategy' => 'static', 'value' => 'Redacted'],
                    'first_name' => ['strategy' => 'static', 'value' => 'Redacted'],
                    'last_name' => ['strategy' => 'static', 'value' => 'Redacted'],
                    'address' => 'null',
                    'telephone' => 'null',
                ],
                'be_users' => [
                    'username' => 'hash',
                    'password' => 'hash',
                    'email' => 'email',
                    'realName' => ['strategy' => 'static', 'value' => 'Redacted'],
                ],
            ],
        };
    }
}
