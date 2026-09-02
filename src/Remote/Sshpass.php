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

use function trim;

/**
 * Sshpass.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Sshpass
{
    /**
     * Whether the local machine can hand rsync a password non-interactively.
     * rsync has no option of its own for one, so without this binary a
     * password-authenticated transfer stops at a prompt nobody answers.
     */
    public function isAvailable(CommandRunner $local): bool
    {
        return '' !== trim($local->run('sshpass -V', true));
    }
}
