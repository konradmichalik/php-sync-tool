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

namespace MoveElevator\DbSyncTool\Remote;

use MoveElevator\DbSyncTool\Config\ClientConfig;
use MoveElevator\DbSyncTool\Exception\DbSyncException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use phpseclib3\System\SSH\Agent;

use function sprintf;

/**
 * SshClientFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SshClientFactory
{
    public function __construct(
        private int $timeout = 600,
        private int $keepAlive = 60,
        private KnownHosts $knownHosts = new KnownHosts(),
        private HostKeyVerifier $verifier = new HostKeyVerifier(),
    ) {}

    public function create(ClientConfig $client, bool $useSshAgent = false, bool $forcePassword = false, bool $strictHostKeyChecking = true): SSH2
    {
        return $this->connect(
            new SSH2($client->host, $client->port, $this->timeout),
            $client,
            $useSshAgent,
            $forcePassword,
            $strictHostKeyChecking,
        );
    }

    public function createSftp(ClientConfig $client, bool $useSshAgent = false, bool $forcePassword = false, bool $strictHostKeyChecking = true): SFTP
    {
        $sftp = new SFTP($client->host, $client->port, $this->timeout);
        $this->connect($sftp, $client, $useSshAgent, $forcePassword, $strictHostKeyChecking);

        return $sftp;
    }

    private function connect(SSH2 $ssh, ClientConfig $client, bool $useSshAgent, bool $forcePassword, bool $strictHostKeyChecking): SSH2
    {
        $ssh->setKeepAlive($this->keepAlive);

        $serverKey = $ssh->getServerPublicHostKey();
        if (false === $serverKey) {
            throw new DbSyncException(sprintf('Could not retrieve the host key from %s', $client->host));
        }
        $this->verifier->assert(
            $this->knownHosts->match($client->host, $client->port, $serverKey),
            $strictHostKeyChecking,
            $client->host,
        );

        if (!$this->authenticate($ssh, $client, $useSshAgent, $forcePassword)) {
            throw new DbSyncException(sprintf('SSH authentication failed for %s@%s', $client->user, $client->host));
        }

        return $ssh;
    }

    private function authenticate(SSH2 $ssh, ClientConfig $client, bool $useSshAgent, bool $forcePassword): bool
    {
        if (!$forcePassword && null !== $client->sshKey) {
            $keyContents = file_get_contents($client->sshKey);
            if (false === $keyContents) {
                throw new DbSyncException(sprintf('SSH key not readable: %s', $client->sshKey));
            }

            $key = PublicKeyLoader::load($keyContents);
            if (!$key instanceof PrivateKey) {
                throw new DbSyncException(sprintf('SSH key is not a usable private key: %s', $client->sshKey));
            }

            return $ssh->login($client->user, $key);
        }

        if (null !== $client->password && '' !== $client->password) {
            return $ssh->login($client->user, $client->password);
        }

        if ($useSshAgent) {
            return $ssh->login($client->user, new Agent());
        }

        throw new DbSyncException(sprintf('No SSH authentication method configured for host %s', $client->host));
    }
}
