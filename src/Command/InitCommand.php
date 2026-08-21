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

use KonradMichalik\SyncTool\Config\{ConfigValidator, ProjectScaffold};
use KonradMichalik\SyncTool\Enum\Framework;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

use function array_map;
use function file_put_contents;
use function getcwd;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;

/**
 * InitCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[AsCommand(name: 'init', description: 'Set up .sync-tool for this project.')]
final class InitCommand extends Command
{
    public function __construct(
        private readonly ProjectScaffold $scaffold = new ProjectScaffold(),
        private readonly ConfigValidator $validator = new ConfigValidator(),
        private readonly ?string $workingDir = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files without asking')
            ->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'Name of the first environment', 'production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->workingDir ?? (getcwd() ?: '.');
        $force = true === $input->getOption('force');

        if (!$input->isInteractive()) {
            $io->error('init asks questions, so it needs a terminal. Write .sync-tool/defaults.yaml and .sync-tool/<name>.yaml yourself, or run it interactively.');

            return Command::FAILURE;
        }

        $io->title('php-sync-tool');

        $framework = $this->askFramework($io, $projectDir);
        $localPath = $io->ask('Path to the credential file on this machine', $this->scaffold->proposePath($projectDir, $framework));
        $environment = $this->askEnvironment($input, $io);

        $io->section(sprintf('The "%s" environment', $environment));
        $host = $io->ask('SSH host');
        $user = $io->ask('SSH user');
        $remotePath = $io->ask('Path to the credential file there', (string) $localPath);

        $files = [
            ProjectScaffold::DIRECTORY.'/defaults.yaml' => $this->scaffold->defaults($framework, ['path' => (string) $localPath]),
            ProjectScaffold::DIRECTORY.'/'.$environment.'.yaml' => $this->scaffold->environment($environment, [
                'host' => (string) $host,
                'user' => (string) $user,
                'path' => (string) $remotePath,
            ]),
        ];

        if (!$this->write($io, $projectDir, $files, $force)) {
            return Command::FAILURE;
        }

        $this->validateWritten($projectDir, $files);

        $io->success(sprintf('Ready. Run "sync-tool pull %s" to pull that database into this project.', $environment));

        return Command::SUCCESS;
    }

    private function askFramework(SymfonyStyle $io, string $projectDir): Framework
    {
        $detected = $this->scaffold->detectFramework($projectDir);
        $names = array_map(static fn (Framework $framework): string => $framework->value, Framework::cases());

        /** @var string $answer */
        $answer = $io->choice('Which framework does this project use?', $names, $detected?->value);

        return Framework::from($answer);
    }

    private function askEnvironment(InputInterface $input, SymfonyStyle $io): string
    {
        /** @var string $default */
        $default = $input->getOption('environment');

        /** @var string $name */
        $name = $io->ask('Name of the first environment', $default);

        return $name;
    }

    /**
     * @param array<string, string> $files
     */
    private function write(SymfonyStyle $io, string $projectDir, array $files, bool $force): bool
    {
        $directory = $projectDir.'/'.ProjectScaffold::DIRECTORY;

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            $io->error(sprintf('Cannot create %s', $directory));

            return false;
        }

        foreach ($files as $relative => $contents) {
            $path = $projectDir.'/'.$relative;

            if (is_file($path) && !$force && !$io->confirm(sprintf('%s exists. Overwrite it?', $relative), false)) {
                $io->warning(sprintf('Kept %s as it was, nothing else was written.', $relative));

                return false;
            }

            // Reporting a file as written and then validating it makes no sense if
            // the write never happened (read-only checkout, full disk).
            if (false === file_put_contents($path, $contents)) {
                $io->error(sprintf('Cannot write %s', $path));

                return false;
            }

            $io->text(sprintf('Wrote %s', $relative));
        }

        return true;
    }

    /**
     * The files are only useful if the tool can read them back.
     *
     * @param array<string, string> $files
     */
    private function validateWritten(string $projectDir, array $files): void
    {
        $merged = [];

        foreach (array_keys($files) as $relative) {
            $parsed = Yaml::parseFile($projectDir.'/'.$relative);

            if (is_array($parsed)) {
                $merged = array_merge($merged, $parsed);
            }
        }

        unset($merged['local']);
        $this->validator->validate($merged);
    }
}
