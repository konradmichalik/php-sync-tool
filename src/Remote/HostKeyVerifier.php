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

use KonradMichalik\SyncTool\Exception\SyncException;

use function sprintf;

/**
 * HostKeyVerifier.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class HostKeyVerifier
{
    public function assert(HostKeyStatus $status, bool $strict, string $host): void
    {
        if (HostKeyStatus::Mismatch === $status) {
            throw new SyncException(sprintf('Host key verification failed for %s: the server key does not match the known_hosts entry (possible man-in-the-middle).', $host));
        }

        if (HostKeyStatus::Unknown === $status && $strict) {
            throw new SyncException(sprintf('Host key verification failed for %s: host is not in known_hosts. Add it (e.g. via ssh-keyscan) or set ssh_strict_host_key_checking: false.', $host));
        }
    }
}
