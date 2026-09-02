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
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\RunnerFactory;

use function escapeshellarg;
use function sprintf;

/**
 * LocalCopyTransferStrategy.
 *
 * Moves the dump between two directories on this machine without rsync, for the
 * case where rsync is absent or was turned off. SFTP is no substitute here:
 * there is no host to connect to.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LocalCopyTransferStrategy implements TransferStrategy
{
    /** @var Closure(string, LogChannel=): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        ?Closure $log = null,
    ) {
        $this->log = $log ?? static function (string $message, LogChannel $channel = LogChannel::Step): void {};
    }

    public function describe(): string
    {
        return ' by copying the file';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        // A directory sync is not just a copy: `--delete` and the exclude patterns
        // have no equivalent in `cp`, and quietly copying everything instead would
        // put back the files the configuration asks to leave out. Saying so beats
        // synchronizing the wrong tree.
        if (!$payload->singleFile) {
            throw new SyncException('Synchronizing files between two local paths needs rsync. Install rsync, or drop --with-files/--files-only to sync the database only.');
        }

        $command = sprintf(
            'cp -- %s %s',
            escapeshellarg($payload->originPath),
            escapeshellarg($payload->targetPath),
        );

        ($this->log)('  $ '.$command, LogChannel::Command);

        $this->runners->local()->run($command);
    }
}
