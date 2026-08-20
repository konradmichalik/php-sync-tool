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
use KonradMichalik\PhpProgress\Terminal\Capabilities;

use function sprintf;

/**
 * LiveSyncProgress.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LiveSyncProgress implements SyncProgress
{
    private Live $live;

    /**
     * @param resource $stream
     */
    public function __construct(int $totalSteps, mixed $stream, ?Capabilities $capabilities = null)
    {
        // Not transient: the finished line is the run's confirmation, so it has to
        // survive. A filled bar plus the elapsed time says more than a separate
        // success message, and it costs no extra lines.
        //
        // The leading spinner turns into the status icon once the line finishes,
        // which is what makes the persisted line read as a verdict rather than a
        // bar that stopped moving.
        $live = Live::bar((float) $totalSteps, 'Sync')
            ->columns('spinner', 'label', 'bar', 'percent', 'count', 'fields', 'elapsed')
            ->to($stream);

        if (null !== $capabilities) {
            $live->caps($capabilities);
        }

        $this->live = $live->start();
    }

    public function enabled(): bool
    {
        return true;
    }

    public function phase(string $label): void
    {
        $this->live->set('phase', $label, sticky: true);
    }

    public function transferPercentage(?int $percent): void
    {
        $this->live->set('rsync', null === $percent ? null : sprintf('%d%%', $percent));
    }

    public function advance(): void
    {
        $this->live->advance();
    }

    public function log(string $line): void
    {
        $this->live->println($line);
    }

    public function succeed(string $text): void
    {
        $this->live->clear('phase');
        $this->live->finish($text);
    }

    public function fail(string $text): void
    {
        $this->live->fail($text);
    }
}
