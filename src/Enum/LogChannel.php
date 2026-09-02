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

/**
 * LogChannel.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
enum LogChannel
{
    /**
     * What the tool is doing right now.
     */
    case Step;

    /**
     * The masked command it runs to do it.
     */
    case Command;

    /**
     * The run continues, but not the way the configuration asked for. Unlike a
     * step, this is worth seeing without `-v`, because the alternative is finding
     * out from the result.
     */
    case Warning;
}
