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

use Closure;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Security\LogSanitizer;
use Symfony\Component\Process\Process;

use function sprintf;
use function trim;

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

    public function run(string $command, bool $allowFail = false, ?Closure $onOutput = null): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout((float) $this->timeout);
        $process->run(null === $onOutput ? null : static function (string $type, string $buffer) use ($onOutput): void {
            if (Process::OUT === $type) {
                $onOutput($buffer);
            }
        });

        // Every non-zero exit is a failure, whether or not the command bothered to
        // explain itself on stderr. Gating this on stderr let a silent `rm`, `test`
        // or client binary report success and the sync continue on bad state.
        if (!$process->isSuccessful() && !$allowFail) {
            throw new SyncException($this->failureMessage($process, $command));
        }

        return rtrim($process->getOutput(), "\n");
    }

    private function failureMessage(Process $process, string $command): string
    {
        $stderr = trim($process->getErrorOutput());

        if ('' !== $stderr) {
            return LogSanitizer::sanitize($stderr);
        }

        return sprintf(
            'Command exited with status %d: %s',
            (int) $process->getExitCode(),
            LogSanitizer::sanitize($command),
        );
    }
}
