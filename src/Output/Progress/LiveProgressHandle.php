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

use KonradMichalik\PhpProgress\Live;

/**
 * LiveProgressHandle.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LiveProgressHandle implements ProgressHandle
{
    public function __construct(
        private Live $live,
    ) {}

    public function progress(float $percent): void
    {
        $this->live->progress($percent);
    }

    public function succeed(string $text): void
    {
        $this->live->finish($text);
    }

    public function fail(string $text): void
    {
        $this->live->fail($text);
    }
}
