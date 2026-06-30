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

/**
 * RunnerFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class RunnerFactory
{
    public function __construct(
        private SshClientFactory $sshClientFactory = new SshClientFactory(),
    ) {}

    public function forClient(ClientConfig $client, bool $useSshAgent = false, bool $forcePassword = false, bool $strictHostKeyChecking = true): CommandRunner
    {
        if ($client->isRemote()) {
            return new SshCommandRunner($this->sshClientFactory->create($client, $useSshAgent, $forcePassword, $strictHostKeyChecking));
        }

        return new LocalCommandRunner();
    }

    public function local(): CommandRunner
    {
        return new LocalCommandRunner();
    }
}
