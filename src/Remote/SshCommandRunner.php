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
use phpseclib3\Net\SSH2;

use function is_int;
use function sprintf;

/**
 * SshCommandRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SshCommandRunner implements CommandRunner
{
    public function __construct(
        private SSH2 $ssh,
    ) {
        // Keeps stderr out of the output callers parse; failure() reads it
        // separately through getStdError().
        $ssh->enableQuietMode();
    }

    /**
     * phpseclib buffers the whole response, so $onOutput has nothing to stream and is ignored.
     */
    public function run(string $command, bool $allowFail = false, ?Closure $onOutput = null): string
    {
        $output = $this->ssh->exec($command);

        // phpseclib catches its own timeout and returns the partial output with no
        // exit status, which would otherwise pass as a finished command.
        if ($this->ssh->isTimeout()) {
            if ($allowFail) {
                return '';
            }

            throw $this->failure('Remote command timed out', $command);
        }

        if (false === $output) {
            if ($allowFail) {
                return '';
            }

            throw $this->failure('Remote command failed', $command);
        }

        $exitStatus = $this->ssh->getExitStatus();
        if (!$allowFail && is_int($exitStatus) && 0 !== $exitStatus) {
            throw $this->failure(sprintf('Remote command exited with status %d', $exitStatus), $command);
        }

        return rtrim((string) $output, "\n");
    }

    /**
     * Masks the command, which can carry credentials, and adds the remote stderr,
     * which names the cause the command alone would not.
     */
    private function failure(string $reason, string $command): SyncException
    {
        $message = sprintf('%s: %s', $reason, LogSanitizer::sanitize($command));
        $stderr = trim((string) $this->ssh->getStdError());

        if ('' !== $stderr) {
            $message .= "\n".LogSanitizer::sanitize($stderr);
        }

        return new SyncException($message);
    }
}
