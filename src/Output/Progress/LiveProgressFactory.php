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

/**
 * LiveProgressFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LiveProgressFactory implements ProgressFactory
{
    /** @var resource */
    private mixed $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        mixed $stream,
        private ?Capabilities $capabilities = null,
    ) {
        $this->stream = $stream;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function spinner(string $label): ProgressHandle
    {
        return $this->handle(Live::spinner($label));
    }

    public function bar(string $label): ProgressHandle
    {
        return $this->handle(Live::bar(100.0, $label)->columns('label', 'bar', 'percent', 'elapsed'));
    }

    private function handle(Live $live): ProgressHandle
    {
        $live->to($this->stream);

        if (null !== $this->capabilities) {
            $live->caps($this->capabilities);
        }

        return new LiveProgressHandle($live->start());
    }
}
