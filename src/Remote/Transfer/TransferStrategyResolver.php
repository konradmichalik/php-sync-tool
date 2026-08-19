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

use Closure;
use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\SyncMode;
use KonradMichalik\SyncTool\Output\Progress\{NullProgress, ProgressFactory};
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RunnerFactory, SshClientFactory};

/**
 * TransferStrategyResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class TransferStrategyResolver
{
    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        private SshClientFactory $sshClientFactory = new SshClientFactory(),
    ) {}

    public function resolve(
        SyncConfig $config,
        SyncMode $mode,
        ?Closure $log = null,
        ProgressFactory $progress = new NullProgress(),
    ): TransferStrategy {
        $originRemote = $config->origin->isRemote();
        $targetRemote = $config->target->isRemote();

        if (!$originRemote && !$targetRemote) {
            return new RsyncTransferStrategy($this->runners, $this->rsync, $log, $progress);
        }

        if (!$config->useRsync) {
            // The SFTP fallback has no progress line: phpseclib transfers block without
            // a byte callback, and its client factory cannot be faked in a unit test.
            return new SftpTransferStrategy($this->sshClientFactory);
        }

        if (SyncMode::Proxy === $mode) {
            return new ProxyTransferStrategy($this->runners, $this->rsync, $log, $progress);
        }

        if (SyncMode::SyncRemote === $mode) {
            return new RemoteCopyTransferStrategy($this->runners, $this->rsync, $log, $progress);
        }

        return new RsyncTransferStrategy($this->runners, $this->rsync, $log, $progress);
    }
}
