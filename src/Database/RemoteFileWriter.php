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

namespace KonradMichalik\SyncTool\Database;

use KonradMichalik\SyncTool\Security\Shell;

use function sprintf;

/**
 * RemoteFileWriter.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RemoteFileWriter
{
    /**
     * Command that recreates the config file on a remote host from a base64 blob,
     * applies 0600 and echoes OK for confirmation.
     *
     * The redirection runs under `umask 077`, so the file is never briefly
     * world-readable between its creation and the `chmod` — it holds a database
     * password, and a shared host's `/tmp` is readable by every other user.
     */
    public function remoteWriteCommand(string $content, string $path): string
    {
        $encoded = base64_encode($content);
        $safePath = Shell::quote($path);

        return sprintf(
            "(umask 077 && echo '%s' | base64 -d > %s) && chmod 600 %s && echo 'OK'",
            $encoded,
            $safePath,
            $safePath,
        );
    }
}
