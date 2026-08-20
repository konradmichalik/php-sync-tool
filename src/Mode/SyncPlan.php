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

use KonradMichalik\SyncTool\Enum\{SyncDirection, SyncOperation};

/**
 * SyncPlan.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SyncPlan
{
    // What a run does, described by the three things the configuration actually
    // determines: which way the data moves, how much of the dump-transfer-import
    // chain runs, and whether both endpoints sit on the same host. The nine mode
    // names the tool has always printed are labels derived from those three.
    public function __construct(
        public SyncDirection $direction,
        public SyncOperation $operation,
        public bool $sameHost = false,
    ) {}

    public function isOriginRemote(): bool
    {
        return $this->direction->originRemote();
    }

    public function isTargetRemote(): bool
    {
        return $this->direction->targetRemote();
    }

    public function isDump(): bool
    {
        return SyncOperation::DumpOnly === $this->operation;
    }

    public function isImport(): bool
    {
        return SyncOperation::ImportOnly === $this->operation;
    }

    /**
     * Two remote endpoints on different hosts: the dump travels origin to local
     * to target, because rsync cannot copy remote to remote directly.
     */
    public function isProxied(): bool
    {
        return SyncDirection::RemoteToRemote === $this->direction && !$this->sameHost;
    }

    /**
     * Two endpoints on the same remote host: the copy happens on that host.
     */
    public function isRemoteCopy(): bool
    {
        return SyncDirection::RemoteToRemote === $this->direction && $this->sameHost;
    }

    /**
     * Everything that writes the target, and is therefore blocked when the
     * target host is protected. A dump writes nothing but a file.
     */
    public function isProtectable(): bool
    {
        return !$this->isDump();
    }

    /**
     * The historical mode name, kept for output and documentation.
     */
    public function label(): string
    {
        return match ($this->operation) {
            SyncOperation::ImportOnly => $this->isTargetRemote() ? 'IMPORT_REMOTE' : 'IMPORT_LOCAL',
            SyncOperation::DumpOnly => $this->isOriginRemote() ? 'DUMP_REMOTE' : 'DUMP_LOCAL',
            SyncOperation::Full => match ($this->direction) {
                SyncDirection::RemoteToLocal => 'RECEIVER',
                SyncDirection::LocalToRemote => 'SENDER',
                SyncDirection::RemoteToRemote => $this->sameHost ? 'SYNC_REMOTE' : 'PROXY',
                SyncDirection::LocalToLocal => 'SYNC_LOCAL',
            },
        };
    }

    public function description(): string
    {
        return match ($this->label()) {
            'RECEIVER' => '(REMOTE ➔ LOCAL)',
            'SENDER' => '(LOCAL ➔ REMOTE)',
            'PROXY' => '(REMOTE ➔ LOCAL ➔ REMOTE)',
            'SYNC_REMOTE' => '(REMOTE ➔ REMOTE)',
            'SYNC_LOCAL' => '(LOCAL ➔ LOCAL)',
            'DUMP_REMOTE' => '(REMOTE, ONLY EXPORT)',
            'DUMP_LOCAL' => '(LOCAL, ONLY EXPORT)',
            'IMPORT_REMOTE' => '(REMOTE, ONLY IMPORT)',
            default => '(LOCAL, ONLY IMPORT)',
        };
    }
}
