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
use KonradMichalik\SyncTool\Command\InitCommand;
use KonradMichalik\SyncTool\Config\{ConfigValidator, ProjectScaffold};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * InitCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class InitCommandTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/db-sync-init-'.uniqid();
        mkdir($this->project.'/config/system', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->project);
    }

    #[Test]
    public function writesBothFilesFromTheAnswers(): void
    {
        $tester = $this->tester();
        // framework, local path, environment name, host, user, remote path
        $tester->setInputs(['TYPO3', 'config/system/settings.php', 'production', 'prod.example.com', 'deploy', '/var/www/prod/config/system/settings.php']);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode, $tester->getDisplay());

        $defaults = Yaml::parseFile($this->project.'/.sync-tool/defaults.yaml');
        self::assertSame('TYPO3', $defaults['type']);
        self::assertSame('config/system/settings.php', $defaults['local']['path']);

        $environment = Yaml::parseFile($this->project.'/.sync-tool/production.yaml');
        self::assertSame('prod.example.com', $environment['origin']['host']);
        self::assertSame('deploy', $environment['origin']['user']);
        self::assertSame('/var/www/prod/config/system/settings.php', $environment['origin']['path']);

        self::assertStringContainsString('sync-tool pull production', $tester->getDisplay());
    }

    #[Test]
    public function proposesTheFrameworkItFindsInTheProject(): void
    {
        file_put_contents($this->project.'/config/system/settings.php', '<?php return [];');

        $tester = $this->tester();
        // empty answer takes the proposed default for every question that has one
        $tester->setInputs(['', '', '', 'prod.example.com', 'deploy', '']);
        $tester->execute([]);

        $defaults = Yaml::parseFile($this->project.'/.sync-tool/defaults.yaml');
        self::assertSame('TYPO3', $defaults['type'], 'detected from config/system/settings.php');
        self::assertSame('config/system/settings.php', $defaults['local']['path']);
    }

    #[Test]
    public function keepsAnExistingFileWhenTheAnswerIsNo(): void
    {
        mkdir($this->project.'/.sync-tool', 0o777, true);
        file_put_contents($this->project.'/.sync-tool/defaults.yaml', "# hand written\n");

        $tester = $this->tester();
        $tester->setInputs(['TYPO3', 'config/system/settings.php', 'production', 'prod.example.com', 'deploy', '/remote/path', 'no']);
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertSame("# hand written\n", file_get_contents($this->project.'/.sync-tool/defaults.yaml'));
        self::assertFileDoesNotExist($this->project.'/.sync-tool/production.yaml');
    }

    #[Test]
    public function overwritesWithForce(): void
    {
        mkdir($this->project.'/.sync-tool', 0o777, true);
        file_put_contents($this->project.'/.sync-tool/defaults.yaml', "# hand written\n");

        $tester = $this->tester();
        $tester->setInputs(['TYPO3', 'config/system/settings.php', 'production', 'prod.example.com', 'deploy', '/remote/path']);
        $exitCode = $tester->execute(['--force' => true]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertStringNotContainsString('hand written', (string) file_get_contents($this->project.'/.sync-tool/defaults.yaml'));
    }

    #[Test]
    public function writesFilesTheToolCanReadBack(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['Symfony', '.env', 'staging', 'staging.example.com', 'deploy', '.env']);
        $tester->execute([]);

        $merged = array_merge(
            Yaml::parseFile($this->project.'/.sync-tool/defaults.yaml'),
            Yaml::parseFile($this->project.'/.sync-tool/staging.yaml'),
        );
        unset($merged['local']);

        $this->expectNotToPerformAssertions();
        (new ConfigValidator())->validate($merged);
    }

    #[Test]
    public function refusesToRunWithoutATerminal(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([], ['interactive' => false]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('needs a terminal', $tester->getDisplay());
        self::assertFileDoesNotExist($this->project.'/.sync-tool/defaults.yaml');
    }

    private function tester(): CommandTester
    {
        $command = new InitCommand(new ProjectScaffold(), new ConfigValidator(), $this->project);
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
