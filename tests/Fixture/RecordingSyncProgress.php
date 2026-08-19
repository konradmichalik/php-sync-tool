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

use KonradMichalik\SyncTool\Output\Progress\SyncProgress;

/**
 * RecordingSyncProgress.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RecordingSyncProgress implements SyncProgress
{
    /** @var list<string> */
    public array $phases = [];

    /** @var list<array{string, string|null}> */
    public array $details = [];

    public int $advances = 0;

    /** @var list<string> */
    public array $logs = [];

    /** @var list<string> */
    public array $succeeded = [];

    /** @var list<string> */
    public array $failed = [];

    public function __construct(private readonly bool $enabled = true) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function phase(string $label): void
    {
        $this->phases[] = $label;
    }

    public function detail(string $key, ?string $value): void
    {
        $this->details[] = [$key, $value];
    }

    public function advance(): void
    {
        ++$this->advances;
    }

    public function log(string $line): void
    {
        $this->logs[] = $line;
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
