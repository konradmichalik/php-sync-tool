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

namespace KonradMichalik\SyncTool;

use KonradMichalik\SyncTool\Command\{InitCommand, PullCommand, PushCommand, SyncCommand};
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\{InputInterface, StringInput};
use Symfony\Component\Console\Output\OutputInterface;

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

        $this->setCommandLoader(new FactoryCommandLoader([
            'sync' => static fn (): SyncCommand => new SyncCommand(),
            'pull' => static fn (): PullCommand => new PullCommand(),
            'push' => static fn (): PushCommand => new PushCommand(),
            'init' => static fn (): InitCommand => new InitCommand(),
        ]));
        $this->setDefaultCommand('sync');
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $first = $input->getFirstArgument();

        // `sync-tool prod` and `sync-tool production local` name a project config or
        // two hosts, not a command. They predate the subcommands, so anything that
        // is not a known command name goes to `sync`, as it always did.
        if (null !== $first && !$this->has($first)) {
            $input = new StringInput('sync '.$input);
        }

        return parent::doRun($input, $output);
    }
}
