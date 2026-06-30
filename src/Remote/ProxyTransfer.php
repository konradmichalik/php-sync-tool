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

use function basename;
use function sys_get_temp_dir;

/**
 * ProxyTransfer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProxyTransfer
{
    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
    ) {}

    /**
     * @return array{0: string, 1: string} pull (origin → local temp), push (local temp → target)
     */
    public function commands(SyncConfig $config, string $originGz, string $localTemp, string $targetGz): array
    {
        $options = $this->rsync->options($config->useRsyncOptions);

        $pull = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->origin, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->origin, $config->useSshpass, $config->origin->jumpHost),
            $this->rsync->userHost($config->origin),
            $originGz,
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
            $targetGz,
        );

        return [$pull, $push];
    }

    public function transfer(SyncConfig $config, string $originGz, string $targetGz): void
    {
        $localTemp = sys_get_temp_dir().'/'.basename($targetGz);
        [$pull, $push] = $this->commands($config, $originGz, $localTemp, $targetGz);

        $local = $this->runners->local();

        try {
            $local->run($pull);
            $local->run($push);
        } finally {
            if (is_file($localTemp)) {
                unlink($localTemp);
            }
        }
    }
}
