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
 * PushCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'push', description: 'Push this project\'s database to a named environment.')]
final class PushCommand extends DirectionalSyncCommand
{
    protected function environmentIsOrigin(): bool
    {
        return false;
    }
}
