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

namespace KonradMichalik\SyncTool\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RunnerFactory};

use function sprintf;

/**
 * RemoteCopyTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class RemoteCopyTransferStrategy implements TransferStrategy
{
    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
    ) {}

    public function describe(): string
    {
        return ' on the remote host';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $options = $this->rsync->options($payload->extraRsyncOptions, $payload->excludePatterns);
        $command = sprintf(
            'rsync %s %s %s',
            $options,
            escapeshellarg($payload->originPath),
            escapeshellarg($payload->targetPath),
        );

        $this->runners->forClient(
            $config->origin,
            $config->sshAgent,
            $config->forcePassword,
            $config->strictHostKeyChecking,
        )->run($command);
    }
}
