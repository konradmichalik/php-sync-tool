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

namespace KonradMichalik\SyncTool\Remote;

use function preg_match;
use function version_compare;

/**
 * RsyncVersion.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class RsyncVersion
{
    /**
     * `--info=progress2` arrived in rsync 3.1.0. Older builds abort on the unknown
     * option, and macOS still ships 2.6.9, so the version decides whether a
     * transfer can report a percentage at all.
     */
    public function supportsProgress2(CommandRunner $local): bool
    {
        $output = $local->run('rsync --version', true);

        if (1 !== preg_match('/version\s+(\d+(?:\.\d+)+)/', $output, $matches)) {
            return false;
        }

        return version_compare($matches[1], '3.1.0', '>=');
    }
}
