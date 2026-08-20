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
use KonradMichalik\SyncTool\Command\SyncCommand;
use KonradMichalik\SyncTool\Config\{ConfigLoader, ConfigResolver};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;

/**
 * SyncPickerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncPickerTest extends TestCase
{
    private string $home;
    private string $work;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/db-sync-picker-'.uniqid();
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
    public function offersEveryProjectConfigAndBothDirectionsPerHost(): void
    {
        $this->writeEverything();

        $tester = $this->tester();
        $tester->setInputs(['0']);
        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('How should this be synchronized?', $output);
        self::assertStringContainsString('staging (project config)', $output);
        self::assertStringContainsString('pull from production', $output);
        self::assertStringContainsString('push to production', $output);
    }

    #[Test]
    public function runsTheChosenPull(): void
    {
        $this->writeEverything();

        $tester = $this->tester();
        // 0 = the project config, 1 = pull from production
        $tester->setInputs(['1']);
        $exitCode = $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('RECEIVER', $output);
        self::assertStringContainsString('prod.example.com', $output);
    }

    #[Test]
    public function runsTheChosenPush(): void
    {
        $this->writeEverything();

        $tester = $this->tester();
        $tester->setInputs(['2']);
        $exitCode = $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('SENDER', $output);
    }

    #[Test]
    public function runsTheChosenProjectConfig(): void
    {
        $this->writeEverything();

        $tester = $this->tester();
        $tester->setInputs(['0']);
        $exitCode = $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('staging.example.com', $output);
    }

    #[Test]
    public function offersNoDirectionsWithoutALocalEndpoint(): void
    {
        $this->writeHosts();
        $this->writeProjectConfig();

        $tester = $this->tester();
        $tester->setInputs(['0']);
        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('staging (project config)', $output);
        self::assertStringNotContainsString('pull from production', $output, 'no local block, no direction');
    }

    #[Test]
    public function keepsFailingWithoutInteraction(): void
    {
        $this->writeEverything();

        $tester = $this->tester();
        $tester->execute(['--dry-run' => true], ['interactive' => false]);

        self::assertStringContainsString('Configuration is missing', $tester->getDisplay());
    }

    #[Test]
    public function keepsFailingWithNothingToOffer(): void
    {
        $tester = $this->tester();
        $tester->execute(['--dry-run' => true]);

        self::assertStringContainsString('Configuration is missing', $tester->getDisplay());
    }

    #[Test]
    public function offersAnEnvironmentOnlyConfigAsADirection(): void
    {
        // This is the shape `sync-tool init` writes: one endpoint, no target.
        file_put_contents(
            $this->work.'/.sync-tool/production.yaml',
            "origin:\n  host: prod.example.com\n  user: deploy\n  db:\n    name: app\n    user: app\n    password: secret\n",
        );
        file_put_contents(
            $this->work.'/.sync-tool/defaults.yaml',
            "local:\n  path: /var/www/local\n  db:\n    name: app_local\n    user: root\n    password: root\n",
        );

        $tester = $this->tester();
        $tester->setInputs(['0']);
        $exitCode = $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('pull from production', $output);
        self::assertStringNotContainsString('production (project config)', $output);
        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('RECEIVER', $output, 'the local block became the target');
    }

    private function writeEverything(): void
    {
        $this->writeHosts();
        $this->writeProjectConfig();
        file_put_contents(
            $this->work.'/.sync-tool/defaults.yaml',
            "local:\n  path: /var/www/local\n  db:\n    name: app_local\n    user: root\n    password: root\n",
        );
    }

    private function writeHosts(): void
    {
        file_put_contents(
            $this->home.'/.sync-tool/hosts.yaml',
            "production:\n  host: prod.example.com\n  user: deploy\n  db:\n    name: app\n    user: app\n    password: secret\n",
        );
    }

    private function writeProjectConfig(): void
    {
        file_put_contents(
            $this->work.'/.sync-tool/staging.yaml',
            "origin:\n  host: staging.example.com\n  user: deploy\n  db:\n    name: app\n    user: app\n    password: secret\ntarget:\n  path: /var/www/local\n  db:\n    name: app_local\n    user: root\n    password: root\n",
        );
    }

    private function tester(): CommandTester
    {
        $command = new SyncCommand(new ConfigResolver(new ConfigLoader(), $this->home, $this->work));
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
