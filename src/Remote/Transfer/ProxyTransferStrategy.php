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
use KonradMichalik\SyncTool\Remote\{RsyncCommandBuilder, RunnerFactory};
use KonradMichalik\SyncTool\Security\LogSanitizer;

use function basename;
use function rtrim;
use function sprintf;
use function sys_get_temp_dir;

/**
 * ProxyTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProxyTransferStrategy implements TransferStrategy
{
    /** @var Closure(string): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        ?Closure $log = null,
    ) {
        $this->log = $log ?? static function (string $message): void {};
    }

    public function describe(): string
    {
        return ' via proxy (origin → local → target)';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $localTemp = sys_get_temp_dir().'/php-sync-tool-'.basename(rtrim($payload->targetPath, '/'));
        $options = $this->rsync->options($payload->extraRsyncOptions, $payload->excludePatterns);

        $pull = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->origin, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->origin, $config->useSshpass, $config->origin->jumpHost),
            $this->rsync->userHost($config->origin),
            $payload->originPath,
            '',
            $localTemp,
        );

        $push = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->target, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->target, $config->useSshpass, $config->target->jumpHost),
            '',
            $localTemp,
            $this->rsync->userHost($config->target),
            $payload->targetPath,
        );

        $local = $this->runners->local();

        try {
            ($this->log)('  $ '.LogSanitizer::sanitize($pull));
            $local->run($pull);
            ($this->log)('  $ '.LogSanitizer::sanitize($push));
            $local->run($push);
        } finally {
            $local->run(sprintf('rm -rf %s', escapeshellarg($localTemp)), true);
        }
    }
}
