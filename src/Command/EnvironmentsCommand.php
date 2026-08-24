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

use KonradMichalik\SyncTool\Config\{ConfigResolver, EnvironmentAssembler};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_map;
use function array_values;
use function sprintf;

/**
 * EnvironmentsCommand.
 *
 * The interactive picker only offers its list on a terminal. This prints the
 * same list, so a script or a colleague can see what is configured.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'environments', description: 'List the synchronizations this project is configured for.')]
final class EnvironmentsCommand extends Command
{
    private readonly EnvironmentAssembler $environments;

    public function __construct(?EnvironmentAssembler $environments = null)
    {
        $this->environments = $environments ?? new EnvironmentAssembler(new ConfigResolver());
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $choices = $this->environments->syncChoices();

        if ([] === $choices) {
            $io->warning('Nothing configured yet. Run "sync-tool init" to describe this project and its first environment.');

            return Command::SUCCESS;
        }

        $io->listing(array_map(
            static fn (array $choice): string => sprintf(
                'sync-tool %s',
                'project' === $choice[0] ? $choice[1] : $choice[0].' '.$choice[1],
            ),
            array_values($choices),
        ));

        return Command::SUCCESS;
    }
}
