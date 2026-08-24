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
    public function count(SyncConfig $config, SyncPlan $plan): int
    {
        $steps = 0;

        if (!$config->filesOnly) {
            if (!$plan->isImport()) {
                ++$steps;
            }

            if (!$plan->isDump()) {
                ++$steps;
            }

            if (!$config->keepDump && !$plan->isDump()) {
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

        if ($config->backupBeforeImport) {
            ++$steps;
        }

        if ([] !== $target->anonymize) {
            ++$steps;
        }

        // Masking and post-import SQL each run as a single batched invocation,
        // so they are one step apiece however many statements they carry.
        if ([] !== array_filter($target->postSql, static fn (string $sql): bool => '' !== $sql)) {
            ++$steps;
        }

        return $steps;
    }
}
