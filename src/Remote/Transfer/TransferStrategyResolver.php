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
use KonradMichalik\SyncTool\Enum\LogChannel;
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RsyncVersion, RunnerFactory, SshClientFactory};

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
        // Shared across every strategy this resolver hands out, so a run asks the
        // local rsync for its version once instead of once per transferred entry.
        private RsyncVersion $rsyncVersion = new RsyncVersion(),
    ) {}

    public function resolve(
        SyncConfig $config,
        SyncPlan $plan,
        ?Closure $log = null,
        SyncProgress $progress = new NullSyncProgress(),
    ): TransferStrategy {
        $anyRemote = $config->origin->isRemote() || $config->target->isRemote();
        $rsyncUsable = $config->useRsync && $this->rsyncVersion->isAvailable($this->runners->local());

        // A machine without rsync used to reach the command anyway and fail there,
        // while the documentation promised a fallback.
        if ($config->useRsync && !$rsyncUsable && null !== $log) {
            $log('rsync not found, falling back to a transfer without it', LogChannel::Warning);
        }

        if (!$rsyncUsable) {
            // The SFTP fallback has no progress line: phpseclib transfers block without
            // a byte callback, and its client factory cannot be faked in a unit test.
            // Between two local paths there is no host to reach, so the file is copied.
            return $anyRemote
                ? new SftpTransferStrategy($this->sshClientFactory)
                : new LocalCopyTransferStrategy($this->runners, $log);
        }

        if ($plan->isProxied()) {
            return new ProxyTransferStrategy($this->runners, $this->rsync, $log);
        }

        if ($plan->isRemoteCopy()) {
            return new RemoteCopyTransferStrategy($this->runners, $this->rsync, $log);
        }

        return new RsyncTransferStrategy($this->runners, $this->rsync, $log, $progress, $this->rsyncVersion);
    }
}
