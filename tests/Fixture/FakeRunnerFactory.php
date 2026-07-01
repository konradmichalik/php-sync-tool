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

namespace KonradMichalik\SyncTool\Tests\Fixture;

use KonradMichalik\SyncTool\Config\ClientConfig;
use KonradMichalik\SyncTool\Remote\{CommandRunner, RunnerFactory};

/**
 * FakeRunnerFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class FakeRunnerFactory extends RunnerFactory
{
    public function __construct(private CommandRunner $runner) {}

    public function forClient(ClientConfig $client, bool $useSshAgent = false, bool $forcePassword = false, bool $strictHostKeyChecking = true): CommandRunner
    {
        return $this->runner;
    }

    public function local(): CommandRunner
    {
        return $this->runner;
    }
}
