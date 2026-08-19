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

use Closure;
use Throwable;

use function sprintf;

/**
 * ProgressScope.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProgressScope
{
    /**
     * Runs $work under $handle: the label persists as a success, a thrown error
     * marks the line as failed and keeps bubbling.
     *
     * @param Closure(): void $work
     */
    public static function run(ProgressHandle $handle, string $label, Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $error) {
            $handle->fail(sprintf('%s failed', $label));

            throw $error;
        }

        $handle->succeed($label);
    }
}
