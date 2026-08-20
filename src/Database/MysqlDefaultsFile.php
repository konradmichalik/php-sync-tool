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

use KonradMichalik\SyncTool\Config\DatabaseConfig;

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
}
