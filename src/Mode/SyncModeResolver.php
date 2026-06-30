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

namespace MoveElevator\DbSyncTool\Mode;

use MoveElevator\DbSyncTool\Config\SyncConfig;
use MoveElevator\DbSyncTool\Enum\SyncMode;
use MoveElevator\DbSyncTool\Exception\DbSyncException;

use function sprintf;

/**
 * SyncModeResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncModeResolver
{
    public function resolve(SyncConfig $cfg): SyncMode
    {
        $mode = SyncMode::Receiver;

        $checks = [
            [SyncMode::Receiver, $this->isReceiver($cfg)],
            [SyncMode::Sender, $this->isSender($cfg)],
            [SyncMode::Proxy, $this->isProxy($cfg)],
            [SyncMode::DumpLocal, $this->isDumpLocal($cfg)],
            [SyncMode::DumpRemote, $this->isDumpRemote($cfg)],
            [SyncMode::ImportLocal, $this->isImportLocal($cfg)],
            [SyncMode::ImportRemote, $this->isImportRemote($cfg)],
            [SyncMode::SyncLocal, $this->isSyncLocal($cfg)],
            [SyncMode::SyncRemote, $this->isSyncRemote($cfg)],
        ];

        foreach ($checks as [$candidate, $matches]) {
            if ($matches) {
                $mode = $candidate;
            }
        }

        return $mode;
    }

    /**
     * @throws DbSyncException if the target is protected against writing modes
     */
    public function checkForProtection(SyncMode $mode, SyncConfig $cfg): void
    {
        if ($mode->isProtectable() && $cfg->target->protect) {
            $host = '' !== $cfg->target->host ? $cfg->target->host : 'local';

            throw new DbSyncException(sprintf('The host %s is protected against the import of a database dump. Please check synchronisation target or adjust the host configuration.', $host));
        }
    }

    private function isProxy(SyncConfig $cfg): bool
    {
        return $cfg->origin->isRemote() && $cfg->target->isRemote();
    }

    private function isReceiver(SyncConfig $cfg): bool
    {
        return $cfg->origin->isRemote() && !$this->isProxy($cfg) && !$this->isSyncRemote($cfg);
    }

    private function isSender(SyncConfig $cfg): bool
    {
        return $cfg->target->isRemote() && !$this->isProxy($cfg) && !$this->isSyncRemote($cfg);
    }

    private function isDumpLocal(SyncConfig $cfg): bool
    {
        return !$cfg->origin->isRemote()
            && !$cfg->target->isRemote()
            && $this->isSameHost($cfg)
            && !$this->isSyncLocal($cfg);
    }

    private function isDumpRemote(SyncConfig $cfg): bool
    {
        return $cfg->origin->isRemote()
            && $cfg->target->isRemote()
            && $this->isSameHost($cfg)
            && !$this->isSyncRemote($cfg);
    }

    private function isImportLocal(SyncConfig $cfg): bool
    {
        return '' !== $cfg->importFile && !$cfg->target->isRemote();
    }

    private function isImportRemote(SyncConfig $cfg): bool
    {
        return '' !== $cfg->importFile && $cfg->target->isRemote();
    }

    private function isSyncLocal(SyncConfig $cfg): bool
    {
        return !$cfg->origin->isRemote()
            && !$cfg->target->isRemote()
            && $this->isSameHost($cfg)
            && $this->isSameSync($cfg);
    }

    private function isSyncRemote(SyncConfig $cfg): bool
    {
        return $cfg->origin->isRemote()
            && $cfg->target->isRemote()
            && $this->isSameHost($cfg)
            && $this->isSameSync($cfg);
    }

    private function isSameHost(SyncConfig $cfg): bool
    {
        return $cfg->origin->host === $cfg->target->host
            && $cfg->origin->port === $cfg->target->port
            && $cfg->origin->user === $cfg->target->user;
    }

    private function isSameSync(SyncConfig $cfg): bool
    {
        if ('' !== $cfg->origin->path && '' !== $cfg->target->path && $cfg->origin->path !== $cfg->target->path) {
            return true;
        }

        if ('' !== $cfg->origin->db->name && '' !== $cfg->target->db->name) {
            if ($cfg->origin->db->name !== $cfg->target->db->name
                || $cfg->origin->db->host !== $cfg->target->db->host) {
                return true;
            }
        }

        return false;
    }
}
