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

use Closure;
use KonradMichalik\SyncTool\Config\ClientConfig;
use KonradMichalik\SyncTool\Enum\LogChannel;

use function implode;
use function sprintf;

/**
 * RunnerFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class RunnerFactory
{
    /**
     * One runner per endpoint for the lifetime of a run. A sync asks for the same
     * endpoint several times (credential read, dump, import, retention) and every
     * miss used to cost a full SSH handshake, which on a measured local run was
     * already about a quarter of the total time and grows with the round-trip.
     *
     * @var array<string, CommandRunner>
     */
    private array $runners = [];

    /** @var Closure(string, LogChannel=): void */
    private readonly Closure $log;

    public function __construct(
        private readonly SshClientFactory $sshClientFactory = new SshClientFactory(),
        ?Closure $log = null,
    ) {
        $this->log = $log ?? static function (string $message, LogChannel $channel = LogChannel::Step): void {};
    }

    public function forClient(ClientConfig $client, bool $useSshAgent = false, bool $forcePassword = false, bool $strictHostKeyChecking = true): CommandRunner
    {
        $key = $this->key($client, $useSshAgent, $forcePassword, $strictHostKeyChecking);

        return $this->runners[$key] ??= $this->create($client, $useSshAgent, $forcePassword, $strictHostKeyChecking);
    }

    public function local(): CommandRunner
    {
        return new LocalCommandRunner();
    }

    private function create(ClientConfig $client, bool $useSshAgent, bool $forcePassword, bool $strictHostKeyChecking): CommandRunner
    {
        if ($client->isRemote()) {
            if (null !== $client->jumpHost) {
                ($this->log)(sprintf('Connecting via SSH to %s@%s (via jump host %s)', $client->user, $client->host, $client->jumpHost->host));

                return new SystemSshCommandRunner($client, $client->jumpHost);
            }

            ($this->log)(sprintf('Connecting via SSH to %s@%s', $client->user, $client->host));

            return new SshCommandRunner($this->sshClientFactory->create($client, $useSshAgent, $forcePassword, $strictHostKeyChecking));
        }

        return new LocalCommandRunner();
    }

    /**
     * Everything that would produce a different connection. Two endpoints that
     * agree on all of it are the same shell as far as a run is concerned.
     *
     * The last three come from the SyncConfig and are therefore identical for
     * every call within a run, so today they can never split the cache. They stay
     * in the key because a key that is too coarse hands back the wrong connection,
     * while one that is too fine only costs a comparison. The honest fix is to
     * move them off the method and onto the factory, where run-scoped settings
     * belong; that needs the caller to build the factory per run, which is part of
     * pulling credential resolution out of Sync.
     */
    private function key(ClientConfig $client, bool $useSshAgent, bool $forcePassword, bool $strictHostKeyChecking): string
    {
        return implode("\0", [
            $client->host,
            (string) $client->port,
            $client->user,
            $client->sshKey ?? '',
            null !== $client->jumpHost ? $client->jumpHost->sshSpec() : '',
            $useSshAgent ? '1' : '0',
            $forcePassword ? '1' : '0',
            $strictHostKeyChecking ? '1' : '0',
        ]);
    }
}
