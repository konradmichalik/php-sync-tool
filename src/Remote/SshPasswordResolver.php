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
 * SshPasswordResolver.
 *
 * Fills in the SSH passwords a run needs but does not have, by asking. Without
 * this, `--force-password` could only succeed when a password was already in the
 * configuration, which is the one case where the flag is pointless.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SshPasswordResolver
{
    /**
     * @param Closure(string): string $ask receives `user@host`, returns the password
     */
    public function resolve(SyncConfig $config, SyncPlan $plan, Closure $ask): SyncConfig
    {
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

        return $config->withClients($origin, $target);
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

        if (null !== $client->sshKey || (null !== $client->password && '' !== $client->password)) {
            return false;
        }

        return !$config->sshAgent;
    }

    private function describe(ClientConfig $client): string
    {
        return sprintf('%s@%s', $client->user, $client->host);
    }
}
