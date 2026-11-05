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
    ) {}

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
     * The command is masked: it carries the credential file's contents on the way
     * to a remote host, and its own `-p` arguments elsewhere. stderr names the
     * cause (access denied, disk full), which the command alone would leave the
     * user to reproduce by hand.
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
