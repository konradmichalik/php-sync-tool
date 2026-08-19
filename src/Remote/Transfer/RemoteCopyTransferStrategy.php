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
use KonradMichalik\SyncTool\Output\Progress\{NullProgress, ProgressFactory, ProgressScope};
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RunnerFactory};
use KonradMichalik\SyncTool\Security\LogSanitizer;

use function sprintf;

/**
 * RemoteCopyTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class RemoteCopyTransferStrategy implements TransferStrategy
{
    /** @var Closure(string): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        ?Closure $log = null,
        private ProgressFactory $progress = new NullProgress(),
    ) {
        $this->log = $log ?? static function (string $message): void {};
    }

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

        ($this->log)('  $ '.LogSanitizer::sanitize($command));

        $runner = $this->runners->forClient(
            $config->origin,
            $config->sshAgent,
            $config->forcePassword,
            $config->strictHostKeyChecking,
        );

        $label = sprintf('Transferring %s', basename($payload->originPath));
        ProgressScope::run($this->progress->spinner($label), $label, static function () use ($runner, $command): void {
            $runner->run($command);
        });
    }
}
