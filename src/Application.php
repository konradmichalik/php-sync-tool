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

namespace MoveElevator\DbSyncTool;

use MoveElevator\DbSyncTool\Command\SyncCommand;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Application.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class Application extends BaseApplication
{
    public const VERSION = '0.1.0-dev';

    public function __construct()
    {
        parent::__construct('php-sync-tool', self::VERSION);

        $command = new SyncCommand();
        $this->addCommand($command);
        $this->setDefaultCommand((string) $command->getName(), true);
    }
}
