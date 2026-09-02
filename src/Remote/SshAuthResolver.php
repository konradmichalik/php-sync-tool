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
use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Mode\SyncPlan;

use function sprintf;

/**
 * SshAuthResolver.
 *
 * Turns what the configuration says about SSH authentication into what the run
 * will actually use: the agent it can see, the passwords it has to ask for, and
 * whether rsync can carry a password at all.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SshAuthResolver
{
    public function __construct(
        private SshAgent $agent = new SshAgent(),
        private Sshpass $sshpass = new Sshpass(),
        private RunnerFactory $runners = new RunnerFactory(),
    ) {}

    /**
     * @param Closure(string): string $ask receives `user@host`, returns the password
     */
    public function resolve(SyncConfig $config, SyncPlan $plan, Closure $ask): SyncConfig
    {
        $config = $this->adoptRunningAgent($config);
        $origin = $config->origin;
        $target = $config->target;

        // An endpoint that is never contacted must not be asked about. A dump-only
        // run never reaches the target, an import-only run never reaches the origin.
        if (!$plan->isImport() && $this->needsPassword($config, $origin)) {
            $origin = $origin->withPassword($ask($this->describe($origin)));
        }

        if (!$plan->isDump() && $this->needsPassword($config, $target)) {
            $target = $target->withPassword($ask($this->describe($target)));
        }

        // After the questions: an endpoint may have just gained the password that
        // makes sshpass necessary.
        return $this->adoptSshpass($config->withClients($origin, $target));
    }

    /**
     * rsync takes no password of its own, so a password-authenticated transfer
     * needs `sshpass` to feed it one. Without this the transfer got a plain
     * `ssh` and stopped at a prompt: a hang in automation, a failure without a
     * terminal.
     *
     * The probe is skipped whenever nothing would use the answer. Note that a
     * configured `use_sshpass: false` cannot be told apart from an unset one, so
     * it does not opt out; key or agent authentication is the way to keep sshpass
     * out of the command.
     */
    private function adoptSshpass(SyncConfig $config): SyncConfig
    {
        if ($config->useSshpass || !$config->useRsync || !$this->hasPasswordAuthenticatedRemote($config)) {
            return $config;
        }

        return $this->sshpass->isAvailable($this->runners->local())
            ? $config->withSshpass(true)
            : $config;
    }

    private function hasPasswordAuthenticatedRemote(SyncConfig $config): bool
    {
        foreach ([$config->origin, $config->target] as $client) {
            if ($client->isRemote() && null === $client->sshKey && null !== $client->password && '' !== $client->password) {
                return true;
            }
        }

        return false;
    }

    /**
     * A loaded agent is a working way in, so it is used without having to be named
     * in the configuration. `ssh_agent: true` stays meaningful as the way to
     * insist on the agent when the probe cannot reach it.
     *
     * The probe is skipped whenever it could not change anything: with
     * `--force-password` the password wins regardless, and an endpoint that has a
     * key or a password of its own never reaches the agent either.
     */
    private function adoptRunningAgent(SyncConfig $config): SyncConfig
    {
        if ($config->sshAgent || $config->forcePassword) {
            return $config;
        }

        foreach ([$config->origin, $config->target] as $client) {
            if ($client->isRemote() && !$this->hasOwnCredentials($client)) {
                return $this->agent->hasKeys() ? $config->withSshAgent(true) : $config;
            }
        }

        return $config;
    }

    /**
     * A key or a password on the endpoint itself, which is what both the agent and
     * the prompt stand back for.
     */
    private function hasOwnCredentials(ClientConfig $client): bool
    {
        return null !== $client->sshKey || (null !== $client->password && '' !== $client->password);
    }

    /**
     * `--force-password` overrides a configured key on purpose, which is what it
     * is for. Otherwise an endpoint is only asked when it has no other way in.
     */
    private function needsPassword(SyncConfig $config, ClientConfig $client): bool
    {
        if (!$client->isRemote()) {
            return false;
        }

        if ($config->forcePassword) {
            return true;
        }

        if ($this->hasOwnCredentials($client)) {
            return false;
        }

        return !$config->sshAgent;
    }

    private function describe(ClientConfig $client): string
    {
        return sprintf('%s@%s', $client->user, $client->host);
    }
}
