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

namespace KonradMichalik\SyncTool\Remote\Transfer;

use function array_pop;
use function preg_match;
use function preg_split;

/**
 * RsyncProgressParser.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncProgressParser
{
    private string $buffer = '';

    /**
     * Feeds a raw output chunk and returns the newest percentage it completed,
     * or null while no further update has arrived. rsync separates its
     * `--info=progress2` updates with carriage returns, so a chunk may carry
     * several updates or cut one in half.
     */
    public function feed(string $chunk): ?float
    {
        $this->buffer .= $chunk;

        $segments = preg_split('/[\r\n]/', $this->buffer);

        if (false === $segments) {
            return null;
        }

        $this->buffer = array_pop($segments) ?? '';

        $percent = null;

        foreach ($segments as $segment) {
            if (1 === preg_match('/(\d+)%/', $segment, $matches)) {
                $percent = (float) $matches[1];
            }
        }

        return $percent;
    }
}
