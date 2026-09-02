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

namespace KonradMichalik\SyncTool\Tests\Unit\Config;

use KonradMichalik\SyncTool\Config\ConfigResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LegacyConfigDirTest.
 *
 * Reading `.db-sync-tool/` is the one compatibility gesture beyond the config
 * file format: configs written for the Python tool keep working in place.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LegacyConfigDirTest extends TestCase
{
    private string $base;
    private string $home;
    private string $work;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/sync-legacy-dir-'.uniqid();
        $this->home = $this->base.'/home';
        $this->work = $this->base.'/work';
        mkdir($this->home, 0o777, true);
        mkdir($this->work, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->base);
    }

    #[Test]
    public function readsGlobalHostsFromTheLegacyDirectory(): void
    {
        $this->write($this->home.'/.db-sync-tool', 'hosts.yaml', "live:\n  host: live.example.com\n  user: deploy\n");

        self::assertArrayHasKey('live', $this->resolver()->getGlobalHosts());
    }

    #[Test]
    public function readsProjectConfigsFromTheLegacyDirectory(): void
    {
        $this->write($this->work.'/.db-sync-tool', 'prod.yaml', "origin: live\ntarget: local\n");

        self::assertArrayHasKey('prod', $this->resolver()->getProjectConfigs());
    }

    #[Test]
    public function theCurrentGlobalDirectoryWinsOverTheLegacyOne(): void
    {
        $this->write($this->home.'/.db-sync-tool', 'hosts.yaml', "legacy:\n  host: legacy.example.com\n");
        $this->write($this->home.'/.sync-tool', 'hosts.yaml', "current:\n  host: current.example.com\n");

        $hosts = $this->resolver()->getGlobalHosts();

        self::assertArrayHasKey('current', $hosts);
        self::assertArrayNotHasKey('legacy', $hosts, 'the legacy directory is a fallback, not a merge source');
    }

    #[Test]
    public function anAncestorsCurrentDirectoryWinsOverACloserLegacyOne(): void
    {
        $sub = $this->work.'/sub';
        mkdir($sub, 0o777, true);
        $this->write($sub.'/.db-sync-tool', 'legacy.yaml', "origin: a\ntarget: b\n");
        $this->write($this->work.'/.sync-tool', 'current.yaml', "origin: a\ntarget: b\n");

        $resolver = new ConfigResolver(homeDir: $this->home, workingDir: $sub);
        $configs = $resolver->getProjectConfigs();

        self::assertArrayHasKey('current', $configs, 'a .sync-tool further up outranks a closer .db-sync-tool');
        self::assertArrayNotHasKey('legacy', $configs);
    }

    #[Test]
    public function theCurrentProjectDirectoryWinsOverTheLegacyOne(): void
    {
        $this->write($this->work.'/.db-sync-tool', 'legacy.yaml', "origin: a\ntarget: b\n");
        $this->write($this->work.'/.sync-tool', 'current.yaml', "origin: a\ntarget: b\n");

        $configs = $this->resolver()->getProjectConfigs();

        self::assertArrayHasKey('current', $configs);
        self::assertArrayNotHasKey('legacy', $configs);
    }

    #[Test]
    public function saysSoWhenAGlobalLegacyDirectoryIsBeingIgnored(): void
    {
        $this->write($this->home.'/.db-sync-tool', 'hosts.yaml', "legacy:\n  host: legacy.example.com\n");
        $this->write($this->home.'/.sync-tool', 'hosts.yaml', "current:\n  host: current.example.com\n");

        $resolver = $this->resolver();
        $resolver->getGlobalHosts();

        self::assertCount(1, $resolver->getDeprecations());
        self::assertStringContainsString($this->home.'/.db-sync-tool', $resolver->getDeprecations()[0]);
        self::assertStringContainsString('ignored', $resolver->getDeprecations()[0]);
    }

    #[Test]
    public function saysSoWhenAProjectLegacyDirectoryIsBeingIgnored(): void
    {
        $this->write($this->work.'/.db-sync-tool', 'legacy.yaml', "origin: a\ntarget: b\n");
        $this->write($this->work.'/.sync-tool', 'current.yaml', "origin: a\ntarget: b\n");

        $resolver = $this->resolver();
        $resolver->getProjectConfigs();

        self::assertCount(1, $resolver->getDeprecations());
        self::assertStringContainsString($this->work.'/.db-sync-tool', $resolver->getDeprecations()[0]);
        self::assertStringContainsString('ignored', $resolver->getDeprecations()[0]);
    }

    #[Test]
    public function reportsEveryLegacyDirectoryItRead(): void
    {
        $this->write($this->home.'/.db-sync-tool', 'hosts.yaml', "live:\n  host: live.example.com\n");
        $this->write($this->work.'/.db-sync-tool', 'prod.yaml', "origin: live\ntarget: local\n");

        $resolver = $this->resolver();
        $resolver->getProjectConfigs();

        $deprecations = $resolver->getDeprecations();

        self::assertCount(2, $deprecations);
        foreach ($deprecations as $deprecation) {
            self::assertStringContainsString('.db-sync-tool', $deprecation);
            self::assertStringContainsString('.sync-tool', $deprecation);
        }
    }

    #[Test]
    public function reportsNothingWithoutALegacyDirectory(): void
    {
        $this->write($this->home.'/.sync-tool', 'hosts.yaml', "live:\n  host: live.example.com\n");

        $resolver = $this->resolver();
        $resolver->getGlobalHosts();

        self::assertSame([], $resolver->getDeprecations());
    }

    #[Test]
    public function reportsNothingBeforeAnythingIsLoaded(): void
    {
        $this->write($this->home.'/.db-sync-tool', 'hosts.yaml', "live:\n  host: live.example.com\n");

        self::assertCount(0, $this->resolver()->getDeprecations());
    }

    private function resolver(): ConfigResolver
    {
        return new ConfigResolver(homeDir: $this->home, workingDir: $this->work);
    }

    private function write(string $dir, string $name, string $contents): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        file_put_contents($dir.'/'.$name, $contents);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
