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

use KonradMichalik\SyncTool\Command\EnvironmentsCommand;
use KonradMichalik\SyncTool\Config\{ConfigLoader, ConfigResolver, EnvironmentAssembler};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * EnvironmentsCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class EnvironmentsCommandTest extends TestCase
{
    private string $project;
    private string $home;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/sync-tool-envs-'.uniqid();
        $this->home = sys_get_temp_dir().'/sync-tool-home-'.uniqid();
        mkdir($this->project.'/.sync-tool', 0o777, true);
        mkdir($this->home, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->project);
        $this->removeTree($this->home);
    }

    #[Test]
    public function listsEveryEnvironmentAsTheCommandThatRunsIt(): void
    {
        file_put_contents($this->project.'/.sync-tool/defaults.yaml', "type: Symfony\nlocal:\n  path: .env\n");
        file_put_contents($this->project.'/.sync-tool/production.yaml', "origin:\n  host: prod.example.com\n");
        file_put_contents($this->project.'/.sync-tool/staging.yaml', "origin:\n  host: stage.example.com\n");

        $tester = $this->tester();
        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode, $display);
        self::assertStringContainsString('sync-tool pull production', $display);
        self::assertStringContainsString('sync-tool push production', $display);
        self::assertStringContainsString('sync-tool pull staging', $display);
    }

    #[Test]
    public function saysSoWhenThereIsNothingConfiguredYet(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('sync-tool init', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $command = new EnvironmentsCommand(new EnvironmentAssembler(
            new ConfigResolver(new ConfigLoader(), $this->home, $this->project),
        ));
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
