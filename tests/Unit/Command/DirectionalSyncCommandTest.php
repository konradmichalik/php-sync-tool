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

namespace KonradMichalik\SyncTool\Tests\Unit\Command;

use KonradMichalik\SyncTool\Application;
use KonradMichalik\SyncTool\Command\{PullCommand, PushCommand};
use KonradMichalik\SyncTool\Config\{ConfigLoader, ConfigResolver};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;

/**
 * DirectionalSyncCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class DirectionalSyncCommandTest extends TestCase
{
    private string $home;
    private string $work;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/db-sync-verb-'.uniqid();
        $this->home = $base.'/home';
        $this->work = $base.'/work';
        mkdir($this->home.'/.sync-tool', 0o777, true);
        mkdir($this->work.'/.sync-tool', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->home));
    }

    #[Test]
    public function pullMakesTheEnvironmentTheOriginAndTheLocalBlockTheTarget(): void
    {
        $this->writeHosts();
        $this->writeLocalBlock();

        $tester = $this->tester(new PullCommand($this->resolver()));
        $exitCode = $tester->execute(['environment' => 'production', '--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('RECEIVER', $output, 'remote to local');
        self::assertStringContainsString('prod.example.com', $output);
    }

    #[Test]
    public function pushMakesTheEnvironmentTheTargetAndTheLocalBlockTheOrigin(): void
    {
        $this->writeHosts();
        $this->writeLocalBlock();

        $tester = $this->tester(new PushCommand($this->resolver()));
        $exitCode = $tester->execute(['environment' => 'production', '--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('SENDER', $output, 'local to remote');
        self::assertStringContainsString('prod.example.com', $output);
    }

    #[Test]
    public function aMissingLocalBlockNamesTheFileThatShouldCarryIt(): void
    {
        $this->writeHosts();

        $tester = $this->tester(new PullCommand($this->resolver()));
        $tester->execute(['environment' => 'production', '--dry-run' => true]);

        self::assertStringContainsString('local', $tester->getDisplay());
        self::assertStringContainsString('defaults.yaml', $tester->getDisplay());
    }

    #[Test]
    public function anUnknownEnvironmentListsTheOnesThatExist(): void
    {
        $this->writeHosts();
        $this->writeLocalBlock();
        file_put_contents($this->work.'/.sync-tool/staging.yaml', "origin:\n  host: staging.example.com\n  user: deploy\n");

        $tester = $this->tester(new PullCommand($this->resolver()));
        $tester->execute(['environment' => 'nope', '--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('nope', $output);
        self::assertStringContainsString('production', $output, 'known hosts are listed');
        self::assertStringContainsString('staging', $output, 'known project configs are listed');
    }

    #[Test]
    public function aProjectConfigCanServeAsTheEnvironment(): void
    {
        $this->writeLocalBlock();
        file_put_contents(
            $this->work.'/.sync-tool/staging.yaml',
            "origin:\n  host: staging.example.com\n  user: deploy\n  db:\n    name: app\n    user: app\n    password: secret\n",
        );

        $tester = $this->tester(new PullCommand($this->resolver()));
        $exitCode = $tester->execute(['environment' => 'staging', '--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('staging.example.com', $output);
    }

    #[Test]
    public function endpointOverridesStillApply(): void
    {
        $this->writeHosts();
        $this->writeLocalBlock();

        $tester = $this->tester(new PullCommand($this->resolver()));
        $tester->execute([
            'environment' => 'production',
            '--origin-host' => 'override.example.com',
            '--dry-run' => true,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertStringContainsString('override.example.com', $tester->getDisplay());
    }

    private function writeHosts(): void
    {
        file_put_contents(
            $this->home.'/.sync-tool/hosts.yaml',
            "production:\n  host: prod.example.com\n  user: deploy\n  path: /var/www/prod\n  db:\n    name: app\n    user: app\n    password: secret\n",
        );
    }

    private function writeLocalBlock(): void
    {
        file_put_contents(
            $this->work.'/.sync-tool/defaults.yaml',
            "local:\n  path: /var/www/local\n  db:\n    name: app_local\n    user: root\n    password: root\n",
        );
    }

    private function resolver(): ConfigResolver
    {
        return new ConfigResolver(new ConfigLoader(), $this->home, $this->work);
    }

    private function tester(PullCommand|PushCommand $command): CommandTester
    {
        // Attach an application so the command sees the global options (--quiet, -v).
        $command->setApplication(new Application());

        return new CommandTester($command);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
