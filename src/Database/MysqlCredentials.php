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

/**
 * MysqlCredentials.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlCredentials
{
    public function defaultsExtraFileArgument(string $configPath): string
    {
        return '--defaults-extra-file='.$configPath;
    }

    public function legacyArguments(DatabaseConfig $db, bool $forcePassword = true): string
    {
        $credentials = "-u'".$db->user."'";

        if ($forcePassword) {
            $credentials .= " -p'".$db->password."'";
        }
        if ('' !== $db->host) {
            $credentials .= " -h'".$db->host."'";
        }
        if (0 !== $db->port) {
            $credentials .= " -P'".$db->port."'";
        }

        return $credentials;
    }
}
