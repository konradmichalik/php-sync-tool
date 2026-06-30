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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * SyncCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir().'/db-sync-cmd-'.uniqid();
        mkdir($dir);
        $this->dir = $dir;
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    #[Test]
    public function dryRunResolvesConfigAndReportsMode(): void
    {
        $file = $this->dir.'/sync.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              host: origin.example.com
              user: deploy
              db:
                name: app
                user: app
                password: secret
            target:
              path: /var/www
              db:
                name: app_local
                user: root
                password: root
            YAML);

        $tester = $this->tester();
        $exitCode = $tester->execute(['--config-file' => $file, '--dry-run' => true]);

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('RECEIVER', $output);
        self::assertStringContainsString('Dry run', $output);
    }

    #[Test]
    public function invalidConfigReportsValidationError(): void
    {
        $file = $this->dir.'/bad.yaml';
        file_put_contents($file, "type: Joomla\n");

        $tester = $this->tester();
        $exitCode = $tester->execute(['--config-file' => $file, '--dry-run' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('validation failed', $tester->getDisplay());
    }

    #[Test]
    public function reverseSwapsOriginAndTarget(): void
    {
        $file = $this->dir.'/rev.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              path: /var/www
              db: {name: local, user: root, password: root}
            target:
              host: prod.example.com
              user: deploy
              db: {name: prod, user: prod, password: prod}
            YAML);

        $tester = $this->tester();
        $tester->execute(['--config-file' => $file, '--reverse' => true, '--dry-run' => true]);

        // Without reverse this is SENDER (local→remote); after the swap the
        // remote prod becomes the origin → RECEIVER (remote→local).
        self::assertStringContainsString('RECEIVER', $tester->getDisplay());
    }

    #[Test]
    public function originHostAndDbOptionsOverrideConfig(): void
    {
        $file = $this->dir.'/ovr.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              path: /var/www
              db: {name: a, user: root, password: root}
            target:
              path: /var/www
              db: {name: b, user: root, password: root}
            YAML);

        $tester = $this->tester();
        $exitCode = $tester->execute([
            '--config-file' => $file,
            '--origin-host' => 'remote.example.com',
            '--origin-db-name' => 'remotedb',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        // origin gained a host via CLI → it is now remote → RECEIVER, target stays local.
        self::assertStringContainsString('remote (remote.example.com)', $output);
        self::assertStringContainsString('RECEIVER', $output);
    }

    #[Test]
    public function logFileAndJsonLogOptionsAreAccepted(): void
    {
        $file = $this->dir.'/log.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              host: origin.example.com
              user: deploy
              db: {name: app, user: app, password: secret}
            target:
              path: /var/www
              db: {name: local, user: root, password: root}
            YAML);

        $tester = $this->tester();
        $exitCode = $tester->execute([
            '--config-file' => $file,
            '--json-log' => true,
            '--log-file' => $this->dir.'/run.log',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('RECEIVER', $tester->getDisplay());
    }

    #[Test]
    public function ciOutputModeEmitsPlainSummary(): void
    {
        $file = $this->dir.'/ci.yaml';
        file_put_contents($file, "origin: {host: o.example.com, user: u, db: {name: a, user: a, password: a}}\ntarget: {path: /var/www, db: {name: b, user: r, password: r}}\n");

        $tester = $this->tester();
        $tester->execute(['--config-file' => $file, '--output' => 'ci', '--dry-run' => true]);

        $out = $tester->getDisplay();
        self::assertStringContainsString('RECEIVER', $out);
        self::assertStringNotContainsString('[OK]', $out); // no SymfonyStyle box in ci mode
    }

    #[Test]
    public function unknownOutputModeFails(): void
    {
        $file = $this->dir.'/bad-out.yaml';
        file_put_contents($file, "origin: {path: /a, db: {name: a, user: a, password: a}}\ntarget: {path: /b, db: {name: b, user: r, password: r}}\n");

        $tester = $this->tester();
        $exit = $tester->execute(['--config-file' => $file, '--output' => 'nope', '--dry-run' => true]);
        self::assertSame(1, $exit);
    }

    #[Test]
    public function quietModeStillShowsErrorsAtQuietVerbosity(): void
    {
        $file = $this->dir.'/q.yaml';
        file_put_contents($file, "type: Joomla\n");

        $tester = $this->tester();
        $exit = $tester->execute(
            ['--config-file' => $file, '--output' => 'quiet', '--dry-run' => true],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
        );

        self::assertSame(1, $exit);
        self::assertStringContainsString('validation failed', $tester->getDisplay());
    }

    #[Test]
    public function importFileWithoutOriginValidatesAndResolves(): void
    {
        $file = $this->dir.'/imp.yaml';
        file_put_contents($file, "target:\n  db: {name: db, user: r, password: r}\n");

        $tester = $this->tester();
        $exit = $tester->execute([
            '--config-file' => $file,
            '--import-file' => '/tmp/seed.sql',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $exit, $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $application = new Application();
        $command = $application->find('sync');

        return new CommandTester($command);
    }
}
