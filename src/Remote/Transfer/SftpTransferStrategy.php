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

use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\SshClientFactory;
use phpseclib3\Net\SFTP;

use function sprintf;

/**
 * SftpTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SftpTransferStrategy implements TransferStrategy
{
    public function __construct(
        private SshClientFactory $factory = new SshClientFactory(),
    ) {}

    public function describe(): string
    {
        return ' via SFTP';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $originRemote = $config->origin->isRemote();
        $targetRemote = $config->target->isRemote();

        if ($originRemote && $targetRemote) {
            throw new SyncException('SFTP fallback does not support remote-to-remote (proxy) transfers; enable rsync for this sync mode.');
        }

        if ($originRemote) {
            $this->download($config, $payload);

            return;
        }

        $this->upload($config, $payload);
    }

    private function download(SyncConfig $config, TransferPayload $payload): void
    {
        $sftp = $this->connect($config, $config->origin);

        if (false === $sftp->get($payload->originPath, $payload->targetPath)) {
            throw new SyncException(sprintf('SFTP download failed: %s', $payload->originPath));
        }
    }

    private function upload(SyncConfig $config, TransferPayload $payload): void
    {
        $sftp = $this->connect($config, $config->target);

        if (false === $sftp->put($payload->targetPath, $payload->originPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new SyncException(sprintf('SFTP upload failed: %s', $payload->targetPath));
        }
    }

    private function connect(SyncConfig $config, ClientConfig $client): SFTP
    {
        return $this->factory->createSftp($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
    }
}
