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

use function gettype;
use function is_scalar;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

/**
 * HostKeyCheckingMode.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum HostKeyCheckingMode: string
{
    case Strict = 'strict';
    case AcceptNew = 'accept-new';
    case Off = 'off';

    public static function fromConfigValue(mixed $value): self
    {
        if (null === $value || true === $value) {
            return self::Strict;
        }

        if (false === $value) {
            return self::Off;
        }

        if (is_string($value) && 'accept-new' === strtolower(trim($value))) {
            return self::AcceptNew;
        }

        throw new ConfigException(sprintf('Unknown ssh_strict_host_key_checking value: %s. Use true, false or accept-new.', is_scalar($value) ? (string) $value : gettype($value)));
    }
}
