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

use KonradMichalik\SyncTool\Config\{ClientConfig, JumpHostConfig};

use function escapeshellarg;
use function implode;
use function sprintf;

/**
 * JumpHostSshCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class JumpHostSshCommand
{
    public function build(ClientConfig $client, JumpHostConfig $jump, string $remoteCommand): string
    {
        $parts = ['ssh', '-J '.$this->jumpSpec($jump)];

        if (null !== $client->sshKey) {
            $parts[] = '-i '.escapeshellarg($client->sshKey);
        }

        $parts[] = '-p '.$client->port;
        $parts[] = escapeshellarg(sprintf('%s@%s', $client->user, $client->host));
        $parts[] = escapeshellarg($remoteCommand);

        return implode(' ', $parts);
    }

    private function jumpSpec(JumpHostConfig $jump): string
    {
        $spec = '' !== $jump->user
            ? sprintf('%s@%s', $jump->user, $jump->host)
            : $jump->host;

        if (22 !== $jump->port) {
            $spec .= ':'.$jump->port;
        }

        return escapeshellarg($spec);
    }
}
