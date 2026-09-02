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

use KonradMichalik\SyncTool\Config\ClientConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\{SFTP, SSH2};
use phpseclib3\System\SSH\Agent;
use Throwable;

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
            throw new SyncException(sprintf('Could not retrieve the host key from %s', $client->host));
        }
        $this->verifier->assert(
            $this->knownHosts->match($client->host, $client->port, $serverKey),
            $strictHostKeyChecking,
            $client->host,
        );

        if (!$this->authenticate($ssh, $client, $useSshAgent, $forcePassword)) {
            throw new SyncException(sprintf('SSH authentication failed for %s@%s', $client->user, $client->host));
        }

        return $ssh;
    }

    private function authenticate(SSH2 $ssh, ClientConfig $client, bool $useSshAgent, bool $forcePassword): bool
    {
        if (!$forcePassword && null !== $client->sshKey) {
            $keyContents = file_get_contents($client->sshKey);
            if (false === $keyContents) {
                throw new SyncException(sprintf('SSH key not readable: %s', $client->sshKey));
            }

            // A passphrase-protected or malformed key makes phpseclib throw from deep
            // inside its loader chain. Left alone that surfaces as a stack trace
            // instead of a sentence naming the file the user has to look at.
            try {
                $key = PublicKeyLoader::load($keyContents);
            } catch (Throwable $exception) {
                throw new SyncException(sprintf('SSH key could not be loaded (%s): %s. Passphrase-protected keys need an SSH agent (ssh_agent: true).', $client->sshKey, $exception->getMessage()), 0, $exception);
            }

            if (!$key instanceof PrivateKey) {
                throw new SyncException(sprintf('SSH key is not a usable private key: %s', $client->sshKey));
            }

            return $ssh->login($client->user, $key);
        }

        if (null !== $client->password && '' !== $client->password) {
            return $ssh->login($client->user, $client->password);
        }

        if ($useSshAgent) {
            // `ssh_agent: true` opts out of the password prompt, so an agent that
            // cannot be reached ends the run here. phpseclib says so by throwing
            // from its own hierarchy, which nothing above catches: the user got a
            // stack trace instead of the one thing they need to know.
            try {
                $agent = new Agent();
            } catch (Throwable $exception) {
                throw new SyncException(sprintf('No SSH agent available for %s (%s). Start one and load a key, or configure ssh_key or password for this endpoint.', $client->host, $exception->getMessage()), 0, $exception);
            }

            return $ssh->login($client->user, $agent);
        }

        throw new SyncException(sprintf('No SSH authentication method configured for host %s', $client->host));
    }
}
