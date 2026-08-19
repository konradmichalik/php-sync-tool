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

namespace KonradMichalik\SyncTool\Output\Progress;

/**
 * ProgressHandle.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface ProgressHandle
{
    /**
     * Reports completion between 0 and 100. Ignored by indeterminate spinners.
     */
    public function progress(float $percent): void;

    public function succeed(string $text): void;

    public function fail(string $text): void;
}
