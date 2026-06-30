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

namespace KonradMichalik\SyncTool\Lifecycle;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\LifecyclePhase;
use KonradMichalik\SyncTool\Remote\CommandRunner;

/**
 * ScriptRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ScriptRunner
{
    /**
     * @return list<string>
     */
    public function scriptsFor(SyncConfig $config, LifecyclePhase $phase): array
    {
        $commands = [];

        foreach ([$config->scripts, $config->origin->scripts, $config->target->scripts] as $scripts) {
            $command = $scripts[$phase->value] ?? '';
            if ('' !== $command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    public function run(CommandRunner $runner, SyncConfig $config, LifecyclePhase $phase): void
    {
        foreach ($this->scriptsFor($config, $phase) as $command) {
            $runner->run($command, LifecyclePhase::Error === $phase);
        }
    }
}
