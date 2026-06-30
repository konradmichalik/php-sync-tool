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

use KonradMichalik\SyncTool\Exception\ConfigException;

use function sprintf;
use function strtolower;

/**
 * OutputMode.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum OutputMode: string
{
    case Interactive = 'interactive';
    case Ci = 'ci';
    case Json = 'json';
    case Quiet = 'quiet';

    public static function fromString(?string $value): self
    {
        if (null === $value || '' === $value) {
            return self::Interactive;
        }

        return self::tryFrom(strtolower($value))
            ?? throw new ConfigException(sprintf('Unknown output mode: %s', $value));
    }
}
