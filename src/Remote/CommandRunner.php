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

namespace KonradMichalik\SyncTool\Remote;

/**
 * CommandRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface CommandRunner
{
    /**
     * @throws \KonradMichalik\SyncTool\Exception\SyncException on failure unless $allowFail
     */
    public function run(string $command, bool $allowFail = false): string;
}
