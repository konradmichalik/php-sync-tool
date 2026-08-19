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
 * NullSyncProgress.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class NullSyncProgress implements SyncProgress
{
    public function enabled(): bool
    {
        return false;
    }

    public function phase(string $label): void {}

    public function transferPercentage(?int $percent): void {}

    public function advance(): void {}

    public function log(string $line): void {}

    public function succeed(string $text): void {}

    public function fail(string $text): void {}
}
