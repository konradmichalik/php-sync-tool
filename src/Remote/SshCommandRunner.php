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

namespace MoveElevator\DbSyncTool\Remote;

use MoveElevator\DbSyncTool\Exception\DbSyncException;
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

    public function run(string $command, bool $allowFail = false): string
    {
        $output = $this->ssh->exec($command);

        if (false === $output) {
            if ($allowFail) {
                return '';
            }

            throw new DbSyncException(sprintf('Remote command failed: %s', $command));
        }

        $exitStatus = $this->ssh->getExitStatus();
        if (!$allowFail && is_int($exitStatus) && 0 !== $exitStatus) {
            throw new DbSyncException(sprintf('Remote command exited with status %d: %s', $exitStatus, $command));
        }

        return rtrim((string) $output, "\n");
    }
}
