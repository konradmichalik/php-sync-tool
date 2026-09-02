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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

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
        $exitCode = $tester->execute(['--config-file' => $file, '--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

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
        $tester->execute(['--config-file' => $file, '--reverse' => true, '--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

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
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

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
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

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

    #[Test]
    public function hostLinkResolvesEndpointFromHostFile(): void
    {
        $hosts = $this->dir.'/hosts.yaml';
        file_put_contents($hosts, "prod:\n  host: prod.example.com\n  user: deploy\n  db: {name: p, user: p, password: p}\n");
        $config = $this->dir.'/linked.yaml';
        file_put_contents($config, "origin:\n  link: \"@prod\"\ntarget:\n  path: /var/www\n  db: {name: l, user: r, password: r}\n");

        $tester = $this->tester();
        $exit = $tester->execute([
            '--config-file' => $config,
            '--host-file' => $hosts,
            '--dry-run' => true,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertStringContainsString('remote (prod.example.com)', $tester->getDisplay());
    }

    #[Test]
    public function resolvesEndpointsFromHostFileArguments(): void
    {
        $hosts = $this->dir.'/pool.yaml';
        file_put_contents($hosts, <<<'YAML'
            prod:
              host: prod.example.com
              user: deploy
              db: {name: p, user: p, password: p}
            staging:
              host: staging.example.com
              user: deploy
              db: {name: s, user: s, password: s}
            YAML);

        $tester = $this->tester();
        $exit = $tester->execute([
            'origin' => 'prod',
            'target' => 'staging',
            '--host-file' => $hosts,
            '--dry-run' => true,
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertStringContainsString('prod.example.com', $tester->getDisplay());
        self::assertStringContainsString('staging.example.com', $tester->getDisplay());
    }

    #[Test]
    public function muteVerboseTransferAndAfterDumpFlagsAreApplied(): void
    {
        $file = $this->dir.'/flags.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              path: /var/www
              db: {name: a, user: root, password: root}
            target:
              path: /var/www2
              db: {name: b, user: root, password: root}
            YAML);

        $tester = $this->tester();
        $exit = $tester->execute(
            [
                '--config-file' => $file,
                '--mute' => true,
                '--no-rsync' => true,
                '--target-after-dump' => '/seed/extra.sql.gz',
                '--dry-run' => true,
            ],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertSame(0, $exit, $tester->getDisplay());
    }

    /**
     * The deployer configurations in the field use the legacy `files.config`
     * shape, so the override has to land in both.
     */
    #[Test]
    public function theFilesTargetReachesBothDocumentedShapes(): void
    {
        $command = new class extends SyncCommand {
            /**
             * @param array<string, mixed> $config
             *
             * @return array<string, mixed>
             */
            public function apply(array $config, string $target): array
            {
                return $this->applyFilesTarget($config, $target);
            }
        };

        $flat = $command->apply(['files' => [['origin' => '/a']]], '/new');
        self::assertSame([['origin' => '/a', 'target' => '/new']], $flat['files']);

        $nested = $command->apply(
            ['files' => ['config' => [['origin' => '/a']], 'option' => ['--delete']]],
            '/new',
        );
        self::assertSame([['origin' => '/a', 'target' => '/new']], $nested['files']['config']);
        self::assertSame(['--delete'], $nested['files']['option'], 'the option list survives');
    }

    /**
     * The override steers a configured entry. With nothing to steer it would
     * silently sync nothing, so it says so instead.
     */
    #[Test]
    public function filesTargetWithoutAnyFileEntryIsReported(): void
    {
        $file = $this->dir.'/nofiles.yaml';
        file_put_contents($file, <<<'YAML'
            origin:
              path: /var/www
              db: {name: a, user: root, password: root}
            target:
              path: /var/www2
              db: {name: b, user: root, password: root}
            YAML);

        $tester = $this->tester();
        $exit = $tester->execute([
            '--config-file' => $file,
            '--files-target' => '/var/www2/fileadmin',
            '--files-only' => true,
            '--dry-run' => true,
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('files', $tester->getDisplay());
    }

    #[Test]
    public function decliningTheConfirmationAbortsBeforeAnyWrite(): void
    {
        $file = $this->dir.'/confirm.yaml';
        file_put_contents($file, "target:\n  db: {name: db, user: r, password: r}\n");

        $tester = $this->tester();
        $tester->setInputs(['no']);
        // ImportLocal is a protectable, purely local mode (no SSH) → the prompt fires
        // and a "no" answer must abort before checkDump/import ever runs.
        $exit = $tester->execute(
            ['--config-file' => $file, '--import-file' => '/tmp/seed.sql'],
            ['interactive' => true],
        );

        self::assertSame(0, $exit, $tester->getDisplay());
        $output = $tester->getDisplay();
        self::assertStringContainsString('This overwrites', $output);
        self::assertStringContainsString('Aborted by user', $output);
    }

    #[Test]
    public function yesFlagSkipsTheConfirmationPrompt(): void
    {
        $file = $this->dir.'/confirm-yes.yaml';
        file_put_contents($file, "target:\n  db: {name: db, user: r, password: r}\n");

        $tester = $this->tester();
        $tester->setInputs(['no']); // queued but must never be consumed
        $exit = $tester->execute(
            ['--config-file' => $file, '--import-file' => '/tmp/does-not-exist.sql', '--yes' => true],
            ['interactive' => true],
        );

        $output = $tester->getDisplay();
        // No prompt, no abort — it proceeds straight into the sync and fails on the
        // missing local dump file (checkDump), proving the confirmation was skipped.
        self::assertSame(1, $exit, $output);
        self::assertStringNotContainsString('This overwrites', $output);
        self::assertStringNotContainsString('Aborted by user', $output);
    }

    /**
     * A deploy has nobody to answer a prompt, so the run has to fail with a
     * sentence naming the endpoint rather than block on a hidden question.
     */
    #[Test]
    public function aNonInteractiveRunIsToldWhatAuthenticationIsMissing(): void
    {
        $file = $this->dir.'/no-auth.yaml';
        file_put_contents($file, "origin:\n  host: o.example.com\n  user: deploy\n  db: {name: a, user: r, password: r}\ntarget:\n  db: {name: b, user: r, password: r}\n");

        $tester = $this->tester();
        $exit = $tester->execute(['--config-file' => $file, '--yes' => true], ['interactive' => false]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('No SSH authentication configured for deploy@o.example.com', $tester->getDisplay());
    }

    /**
     * The prompt is the point of the flag. Without it, --force-password could only
     * work when a password was already configured.
     */
    #[Test]
    public function forcePasswordAsksForAPasswordOnATerminal(): void
    {
        $file = $this->dir.'/force-password.yaml';
        file_put_contents($file, "origin:\n  host: o.example.com\n  user: deploy\n  ssh_key: /dev/null\n  db: {name: a, user: r, password: r}\ntarget:\n  db: {name: b, user: r, password: r}\n");

        $tester = $this->tester();
        $tester->setInputs(['s3cret']);

        try {
            $tester->execute(
                ['--config-file' => $file, '--force-password' => true, '--yes' => true],
                ['interactive' => true],
            );
        } catch (Throwable) {
            // The answer is accepted and the run goes on to connect, which this host
            // cannot do. The prompt having happened is what is under test.
        }

        self::assertStringContainsString('SSH password for deploy@o.example.com', $tester->getDisplay());
    }

    #[Test]
    public function aDryRunIsNeverAskedForAPassword(): void
    {
        $file = $this->dir.'/dry-run-auth.yaml';
        file_put_contents($file, "origin:\n  host: o.example.com\n  user: deploy\n  db: {name: a, user: r, password: r}\ntarget:\n  db: {name: b, user: r, password: r}\n");

        $tester = $this->tester();
        $exit = $tester->execute(['--config-file' => $file, '--dry-run' => true], ['interactive' => false]);

        self::assertSame(0, $exit, $tester->getDisplay());
        self::assertStringNotContainsString('No SSH authentication', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $application = new Application();
        $command = $application->find('sync');

        return new CommandTester($command);
    }
}
