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

namespace KonradMichalik\SyncTool\Mode;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\{SyncDirection, SyncOperation};
use KonradMichalik\SyncTool\Exception\SyncException;

use function sprintf;

/**
 * SyncModeResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncModeResolver
{
    public function resolve(SyncConfig $cfg): SyncPlan
    {
        $sameHost = $this->isSameHost($cfg);

        return new SyncPlan(
            SyncDirection::between($cfg->origin->isRemote(), $cfg->target->isRemote()),
            $this->operation($cfg, $sameHost),
            $sameHost,
        );
    }

    /**
     * @throws SyncException if the target is protected against writing modes
     */
    public function checkForProtection(SyncPlan $plan, SyncConfig $cfg): void
    {
        if ($plan->isProtectable() && $cfg->target->protect) {
            $host = '' !== $cfg->target->host ? $cfg->target->host : 'local';

            throw new SyncException(sprintf('The host %s is protected against the import of a database dump. Please check synchronisation target or adjust the host configuration.', $host));
        }
    }

    private function operation(SyncConfig $cfg, bool $sameHost): SyncOperation
    {
        // Two endpoints on one host pointing at different data are a real sync,
        // even with an import file configured — that precedence is inherited.
        if ($sameHost && $this->dataDiffers($cfg)) {
            return SyncOperation::Full;
        }

        if ('' !== $cfg->importFile) {
            return SyncOperation::ImportOnly;
        }

        // One host, one database: there is nothing to transfer, only to export.
        return $sameHost ? SyncOperation::DumpOnly : SyncOperation::Full;
    }

    private function isSameHost(SyncConfig $cfg): bool
    {
        return $cfg->origin->host === $cfg->target->host
            && $cfg->origin->port === $cfg->target->port
            && $cfg->origin->user === $cfg->target->user;
    }

    /**
     * Whether the two endpoints address different data at all: a different
     * config path, or a different database.
     */
    private function dataDiffers(SyncConfig $cfg): bool
    {
        if ('' !== $cfg->origin->path && '' !== $cfg->target->path && $cfg->origin->path !== $cfg->target->path) {
            return true;
        }

        if ('' !== $cfg->origin->db->name && '' !== $cfg->target->db->name) {
            return $cfg->origin->db->name !== $cfg->target->db->name
                || $cfg->origin->db->host !== $cfg->target->db->host;
        }

        return false;
    }
}
