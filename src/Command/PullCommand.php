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

namespace KonradMichalik\SyncTool\Command;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * PullCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'pull', description: 'Pull a database from a named environment into this project.')]
final class PullCommand extends DirectionalSyncCommand
{
    protected function environmentIsOrigin(): bool
    {
        return true;
    }
}
