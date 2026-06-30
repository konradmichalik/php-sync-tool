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

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use phpseclib3\Net\SFTP;

use function sprintf;

/**
 * SftpTransfer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SftpTransfer
{
    public function __construct(
        private SshClientFactory $factory = new SshClientFactory(),
    ) {}

    public static function direction(bool $originRemote, bool $targetRemote): SftpDirection
    {
        if ($originRemote && $targetRemote) {
            throw new SyncException('SFTP fallback does not support remote-to-remote (proxy) transfers; enable rsync for this sync mode.');
        }

        return $originRemote ? SftpDirection::Download : SftpDirection::Upload;
    }

    public function transfer(SyncConfig $config, string $originGz, string $targetGz): void
    {
        $direction = self::direction($config->origin->isRemote(), $config->target->isRemote());

        if (SftpDirection::Download === $direction) {
            $sftp = $this->factory->createSftp($config->origin, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
            if (false === $sftp->get($originGz, $targetGz)) {
                throw new SyncException(sprintf('SFTP download failed: %s', $originGz));
            }

            return;
        }

        $sftp = $this->factory->createSftp($config->target, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
        if (false === $sftp->put($targetGz, $originGz, SFTP::SOURCE_LOCAL_FILE)) {
            throw new SyncException(sprintf('SFTP upload failed: %s', $targetGz));
        }
    }
}
