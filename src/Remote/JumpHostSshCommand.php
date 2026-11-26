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
use KonradMichalik\SyncTool\Enum\HostKeyCheckingMode;

use function escapeshellarg;
use function implode;
use function md5;
use function sprintf;
use function substr;

/**
 * JumpHostSshCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class JumpHostSshCommand
{
    public function build(ClientConfig $client, JumpHostConfig $jump, string $remoteCommand, HostKeyCheckingMode $hostKeyChecking, ?string $controlDirectory = null): string
    {
        $parts = ['ssh', '-J '.escapeshellarg($jump->sshSpec())];

        if (null !== $client->sshKey) {
            $parts[] = '-i '.escapeshellarg($client->sshKey);
        }

        $parts[] = '-p '.$client->port;
        $parts[] = '-o StrictHostKeyChecking='.match ($hostKeyChecking) {
            HostKeyCheckingMode::Strict => 'yes',
            HostKeyCheckingMode::Off => 'no',
            HostKeyCheckingMode::AcceptNew => 'accept-new',
        };
        // Drops a silently dead connection after two minutes instead of hanging.
        $parts[] = '-o ServerAliveInterval=30 -o ServerAliveCountMax=4';

        // A sync issues many commands per endpoint, and each one would otherwise pay two handshakes.
        // `%C` covers host, port and user only, so key and host key mode go into the name: a connection
        // opened without the host key check must not serve a run that asks for it.
        if (null !== $controlDirectory) {
            $identity = substr(md5(($client->sshKey ?? '').'|'.$hostKeyChecking->value), 0, 8);
            $parts[] = '-o ControlMaster=auto -o ControlPersist=60 -o ControlPath='.escapeshellarg($controlDirectory.'/'.$identity.'-%C');
        }

        $parts[] = escapeshellarg(sprintf('%s@%s', $client->user, $client->host));
        $parts[] = escapeshellarg($remoteCommand);

        return implode(' ', $parts);
    }
}
