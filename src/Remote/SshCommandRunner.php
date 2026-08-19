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

        if (false === $output) {
            if ($allowFail) {
                return '';
            }

            throw new SyncException(sprintf('Remote command failed: %s', $command));
        }

        $exitStatus = $this->ssh->getExitStatus();
        if (!$allowFail && is_int($exitStatus) && 0 !== $exitStatus) {
            throw new SyncException(sprintf('Remote command exited with status %d: %s', $exitStatus, $command));
        }

        return rtrim((string) $output, "\n");
    }
}
