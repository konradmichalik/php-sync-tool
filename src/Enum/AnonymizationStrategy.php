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
 * AnonymizationStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum AnonymizationStrategy: string
{
    case Nullify = 'null';
    case StaticValue = 'static';
    case Hash = 'hash';
    case Email = 'email';

    /**
     * Domain for addresses produced by the Email strategy. RFC 2606 reserves
     * `.invalid`, so nothing sent to a masked address can leave the machine.
     */
    public const MASKED_MAIL_DOMAIN = '@example.invalid';

    public static function fromConfigValue(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }

    public function requiresValue(): bool
    {
        return self::StaticValue === $this;
    }
}
