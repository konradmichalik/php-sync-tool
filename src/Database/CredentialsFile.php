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

use function sprintf;

/**
 * CredentialsFile.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class CredentialsFile
{
    /**
     * Command that recreates the config file on a remote host from a base64 blob,
     * applies 0600 and echoes OK for confirmation.
     */
    public function remoteWriteCommand(string $content, string $path): string
    {
        $encoded = base64_encode($content);

        return sprintf(
            "echo '%s' | base64 -d > %s && chmod 600 %s && echo 'OK'",
            $encoded,
            $path,
            $path,
        );
    }
}
