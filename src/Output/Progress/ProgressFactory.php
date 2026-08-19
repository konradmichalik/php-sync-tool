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
 * ProgressFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface ProgressFactory
{
    /**
     * Whether anything is rendered at all. Callers use this to skip work that
     * only exists to feed a progress display.
     */
    public function enabled(): bool;

    public function spinner(string $label): ProgressHandle;

    public function bar(string $label): ProgressHandle;
}
