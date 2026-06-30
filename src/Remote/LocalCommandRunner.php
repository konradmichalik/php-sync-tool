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

use KonradMichalik\SyncTool\Exception\SyncException;
use Symfony\Component\Process\Process;

/**
 * LocalCommandRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LocalCommandRunner implements CommandRunner
{
    public function __construct(
        private int $timeout = 600,
    ) {}

    public function run(string $command, bool $allowFail = false): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout((float) $this->timeout);
        $process->run();

        if (!$process->isSuccessful() && '' !== trim($process->getErrorOutput()) && !$allowFail) {
            throw new SyncException($process->getErrorOutput());
        }

        return rtrim($process->getOutput(), "\n");
    }
}
