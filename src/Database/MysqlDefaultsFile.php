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

namespace MoveElevator\DbSyncTool\Database;

use MoveElevator\DbSyncTool\Config\DatabaseConfig;

use function sprintf;

/**
 * MysqlDefaultsFile.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlDefaultsFile
{
    public function buildContent(DatabaseConfig $db): string
    {
        $escapedPassword = str_replace(['\\', '"'], ['\\\\', '\\"'], $db->password);

        $content = "[client]\n";
        $content .= 'user='.$db->user."\n";
        $content .= 'password="'.$escapedPassword."\"\n";
        if ('' !== $db->host) {
            $content .= 'host='.$db->host."\n";
        }
        if (0 !== $db->port) {
            $content .= 'port='.$db->port."\n";
        }

        if ($db->sslDisabled) {
            $content .= "ssl=false\n";
        }

        return $content;
    }

    public function generatePath(): string
    {
        return sprintf('/tmp/.my_%s.cnf', bin2hex(random_bytes(8)));
    }

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
