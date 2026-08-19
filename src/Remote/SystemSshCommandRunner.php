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
use KonradMichalik\SyncTool\Config\{ClientConfig, JumpHostConfig};

/**
 * SystemSshCommandRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SystemSshCommandRunner implements CommandRunner
{
    public function __construct(
        private ClientConfig $client,
        private JumpHostConfig $jump,
        private JumpHostSshCommand $builder = new JumpHostSshCommand(),
        private LocalCommandRunner $local = new LocalCommandRunner(),
    ) {}

    public function run(string $command, bool $allowFail = false, ?Closure $onOutput = null): string
    {
        return $this->local->run($this->builder->build($this->client, $this->jump, $command), $allowFail, $onOutput);
    }
}
