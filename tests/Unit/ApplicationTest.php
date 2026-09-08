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

namespace KonradMichalik\SyncTool\Tests\Unit;

use KonradMichalik\SyncTool\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * ApplicationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ApplicationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(true);
    }

    #[Test]
    public function registersTheSyncCommandAndItsVerbs(): void
    {
        self::assertTrue($this->application->has('sync'));
        self::assertTrue($this->application->has('pull'));
        self::assertTrue($this->application->has('push'));
        self::assertTrue($this->application->has('init'));
    }

    #[Test]
    public function aVerbIsTreatedAsACommandNotAsAHostName(): void
    {
        $output = $this->execute('pull --help');

        self::assertStringContainsString('pull', $output);
        self::assertStringContainsString('environment', $output, 'the pull help mentions its argument');
    }

    #[Test]
    public function aHostNameAsFirstArgumentStillReachesTheSyncCommand(): void
    {
        // `sync-tool production local` predates the subcommands and must keep working.
        $output = $this->execute('some-host another-host');

        self::assertStringNotContainsString('is not defined', $output);
        self::assertStringContainsString('some-host', $output, 'the sync command resolved the name as a host');
    }

    #[Test]
    public function aProjectConfigNameAsFirstArgumentStillReachesTheSyncCommand(): void
    {
        $output = $this->execute('some-project-config');

        self::assertStringNotContainsString('is not defined', $output);
    }

    #[Test]
    public function anExplicitFileStillReachesTheSyncCommand(): void
    {
        $output = $this->execute('-f /definitely/missing/config.yaml');

        self::assertStringNotContainsString('is not defined', $output);
        self::assertStringContainsString('/definitely/missing/config.yaml', $output);
    }

    #[Test]
    public function globalOptionsStillReachTheApplicationItself(): void
    {
        self::assertStringContainsString('php-sync-tool', $this->execute('--version'));
        self::assertStringContainsString('sync', $this->execute('list'));
    }

    /**
     * db-sync-tool's `-kd <dir> -dn <name>` has no direct Symfony Console
     * equivalent (a multi-character shortcut isn't parsed as one flag), so it
     * is rewritten on the raw process argv before Symfony sees it. This only
     * happens on the real argv path (`run(null, ...)`), which is why this
     * test cannot use StringInput like the others.
     */
    #[Test]
    public function legacyShortDumpFlagsFromDbSyncToolAreRewrittenBeforeParsing(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['bin/sync-tool', '-f', '/definitely/missing/config.yaml', '-kd', '/tmp/dumps', '-dn', 'custom.sql'];

        try {
            $output = new BufferedOutput();
            $this->application->run(null, $output);
            $display = $output->fetch();
        } finally {
            if (null === $originalArgv) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
        }

        self::assertStringNotContainsString('option does not exist', $display);
        self::assertStringContainsString('/definitely/missing/config.yaml', $display, 'legacy flags were rewritten and SyncCommand was reached');
    }

    private function execute(string $command): string
    {
        $output = new BufferedOutput();
        $this->application->run(new StringInput($command), $output);

        return $output->fetch();
    }
}
