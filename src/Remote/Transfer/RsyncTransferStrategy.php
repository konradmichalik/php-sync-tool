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
use KonradMichalik\SyncTool\Output\Progress\{NullSyncProgress, SyncProgress};
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RsyncVersion, RunnerFactory};
use KonradMichalik\SyncTool\Security\LogSanitizer;

/**
 * RsyncTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class RsyncTransferStrategy implements TransferStrategy
{
    /** @var Closure(string, LogChannel=): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        ?Closure $log = null,
        private SyncProgress $progress = new NullSyncProgress(),
        private RsyncVersion $rsyncVersion = new RsyncVersion(),
    ) {
        $this->log = $log ?? static function (string $message, LogChannel $channel = LogChannel::Step): void {};
    }

    public function describe(): string
    {
        return '';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $remoteClient = $config->origin->isRemote() ? $config->origin : $config->target;
        $local = $this->runners->local();
        $withPercentage = $this->progress->enabled() && $this->rsyncVersion->supportsProgress2($local);

        $command = $this->rsync->build(
            $this->rsync->passwordEnvironment($remoteClient, $config->useSshpass),
            $this->rsync->options($payload->extraRsyncOptions, $payload->excludePatterns, $withPercentage),
            $this->rsync->authorization($remoteClient, $config->useSshpass, $remoteClient->jumpHost),
            $this->rsync->userHost($config->origin),
            $payload->originPath,
            $this->rsync->userHost($config->target),
            $payload->targetPath,
        );

        ($this->log)('  $ '.LogSanitizer::sanitize($command), LogChannel::Command);

        try {
            $local->run($command, onOutput: $withPercentage ? $this->percentageReader() : null);
        } finally {
            $this->progress->transferPercentage(null);
        }
    }

    /**
     * @return Closure(string): void
     */
    private function percentageReader(): Closure
    {
        $parser = new RsyncProgressParser();
        $progress = $this->progress;

        return static function (string $chunk) use ($parser, $progress): void {
            $percent = $parser->feed($chunk);

            if (null !== $percent) {
                $progress->transferPercentage((int) $percent);
            }
        };
    }
}
