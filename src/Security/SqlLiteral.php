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

namespace KonradMichalik\SyncTool\Security;

use function str_replace;

/**
 * SqlLiteral.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SqlLiteral
{
    /**
     * A SQL string literal: single quotes doubled, the way both systems expect.
     */
    public static function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
