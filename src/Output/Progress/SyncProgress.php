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
 * SyncProgress.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface SyncProgress
{
    /**
     * Whether anything is rendered at all. Callers use this to skip work that
     * only exists to feed the display.
     */
    public function enabled(): bool;

    public function phase(string $label): void;

    /**
     * Adds extra information to the line, or removes it again with a null value.
     */
    public function detail(string $key, ?string $value): void;

    public function advance(): void;

    /**
     * Writes a log line above the live line instead of overwriting it.
     */
    public function log(string $line): void;

    public function succeed(string $text): void;

    public function fail(string $text): void;
}
