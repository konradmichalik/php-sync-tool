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

use function fileowner;
use function fileperms;
use function is_dir;
use function is_link;
use function mkdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * SshControlDirectory.
 *
 * Where the sockets of a shared connection live. Whoever can reach a socket is
 * logged in as its owner, so the directory has to be the current user's alone.
 * A shared `/tmp` lets another user create the path first, which is why an
 * existing directory is checked instead of trusted.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SshControlDirectory
{
    /**
     * `/tmp` keeps the socket path short: a Unix socket path is limited to about
     * 100 characters, and the per-user temp directory of macOS alone takes half.
     *
     * @return string|null the directory, or null when there is no safe one and the connection is not shared
     */
    public static function ensure(string $base = '/tmp'): ?string
    {
        $uid = self::processUid();

        if (null === $uid) {
            return null;
        }

        $path = $base.'/php-sync-tool-ssh-'.$uid;

        if (!is_dir($path) && !@mkdir($path, 0o700) && !is_dir($path)) {
            return null;
        }

        if (is_link($path) || fileowner($path) !== $uid || 0 !== (fileperms($path) & 0o077)) {
            return null;
        }

        return $path;
    }

    /**
     * The user the process runs as. `getmyuid()` names the owner of the script file
     * instead, which is somebody else for a root-owned global install, and
     * `posix_geteuid()` needs an extension the package does not require. A file the
     * process creates belongs to the user it runs as.
     */
    private static function processUid(): ?int
    {
        $probe = @tempnam(sys_get_temp_dir(), 'sync-tool-uid');

        if (false === $probe) {
            return null;
        }

        $uid = fileowner($probe);
        @unlink($probe);

        return false === $uid ? null : $uid;
    }
}
