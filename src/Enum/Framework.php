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

namespace MoveElevator\DbSyncTool\Enum;

use MoveElevator\DbSyncTool\Exception\ConfigException;

use function sprintf;

/**
 * Framework.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum Framework: string
{
    case Typo3 = 'TYPO3';
    case Symfony = 'Symfony';
    case Drupal = 'Drupal';
    case WordPress = 'WordPress';
    case Laravel = 'Laravel';

    /**
     * Resolve a framework from a user-provided type string, case-insensitively.
     *
     * @throws ConfigException on an unknown framework type
     */
    public static function fromString(string $type): self
    {
        foreach (self::cases() as $framework) {
            if (0 === strcasecmp($framework->value, $type)) {
                return $framework;
            }
        }

        throw new ConfigException(sprintf('Unknown framework type: %s', $type));
    }
}
