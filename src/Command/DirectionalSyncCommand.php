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

use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * DirectionalSyncCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
abstract class DirectionalSyncCommand extends SyncCommand
{
    // One named environment plus this machine: the verb says which side is which.
    protected function configure(): void
    {
        $this->addArgument(
            'environment',
            InputArgument::REQUIRED,
            'Name of the environment: a host from the host file or a project config',
        );

        $this->configureSharedOptions();
    }

    /**
     * Whether the named environment is the side data comes from.
     */
    abstract protected function environmentIsOrigin(): bool;

    protected function buildConfig(InputInterface $input, OutputInterface $output): array
    {
        /** @var string $environment */
        $environment = $input->getArgument('environment');

        return $this->finishConfig(
            $this->environments->assemble($environment, $this->environmentIsOrigin()),
            $input,
            $output,
        );
    }
}
