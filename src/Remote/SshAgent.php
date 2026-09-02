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

use phpseclib3\System\SSH\Agent;
use Throwable;

/**
 * SshAgent.
 *
 * Deliberately not final: it talks to a socket, so a test needs to stand in for
 * it.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class SshAgent
{
    /**
     * Whether an agent is reachable and holds at least one key. An agent that is
     * running but empty is no way in, so it counts as absent.
     *
     * Anything the agent connection throws (no `SSH_AUTH_SOCK`, a stale socket, a
     * refused connection) means the same thing to the caller, so it is answered
     * rather than raised: the caller has other methods to fall back on.
     */
    public function hasKeys(): bool
    {
        try {
            return [] !== (new Agent())->requestIdentities();
        } catch (Throwable) {
            return false;
        }
    }
}
