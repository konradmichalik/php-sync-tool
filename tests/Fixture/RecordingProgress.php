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

namespace KonradMichalik\SyncTool\Tests\Fixture;

use KonradMichalik\SyncTool\Output\Progress\{ProgressFactory, ProgressHandle};

/**
 * RecordingProgress.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RecordingProgress implements ProgressFactory, ProgressHandle
{
    /** @var list<string> */
    public array $bars = [];

    /** @var list<string> */
    public array $spinners = [];

    /** @var list<float> */
    public array $percents = [];

    /** @var list<string> */
    public array $succeeded = [];

    /** @var list<string> */
    public array $failed = [];

    public function __construct(private readonly bool $enabled = true) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function spinner(string $label): ProgressHandle
    {
        $this->spinners[] = $label;

        return $this;
    }

    public function bar(string $label): ProgressHandle
    {
        $this->bars[] = $label;

        return $this;
    }

    public function progress(float $percent): void
    {
        $this->percents[] = $percent;
    }

    public function succeed(string $text): void
    {
        $this->succeeded[] = $text;
    }

    public function fail(string $text): void
    {
        $this->failed[] = $text;
    }
}
