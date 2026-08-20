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
use KonradMichalik\SyncTool\Enum\SyncMode;

use function array_filter;
use function count;
use function max;

/**
 * SyncSteps.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SyncSteps
{
    /**
     * How many reported steps a run will take. Mirrors the branching in Sync::run()
     * and FileSync::sync(), so the progress display knows its total up front.
     */
    public function count(SyncConfig $config, SyncMode $mode): int
    {
        $steps = 0;

        if (!$config->filesOnly) {
            if (!$mode->isImport()) {
                ++$steps;
            }

            if (!$mode->isDump()) {
                ++$steps;
            }

            if (!$config->keepDump && !$mode->isDump()) {
                ++$steps;
                $steps += $this->targetImportSteps($config);
            }
        }

        if ($config->filesOnly || $config->withFiles) {
            $steps += count($config->files);
        }

        return max(1, $steps);
    }

    private function targetImportSteps(SyncConfig $config): int
    {
        $target = $config->target;
        $steps = null !== $target->afterDump && '' !== $target->afterDump ? 1 : 0;

        if ([] !== $target->anonymize) {
            ++$steps;
        }

        return $steps + count(array_filter($target->postSql, static fn (string $sql): bool => '' !== $sql));
    }
}
