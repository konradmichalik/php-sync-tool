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
use KonradMichalik\SyncTool\Exception\{ConfigException, NoConfigFoundException};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * ConfigResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigResolverTest extends TestCase
{
    private string $home;
    private string $work;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir().'/db-sync-resolver-'.uniqid();
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
    public function resolvesExplicitFile(): void
    {
        $file = $this->work.'/custom.yaml';
        file_put_contents($file, "origin: {}\n");

        $resolved = $this->resolver()->resolve(configFile: $file);

        self::assertSame($file, $resolved->configFile);
        self::assertStringContainsString('explicit file', $resolved->source);
    }

    #[Test]
    public function missingExplicitFileThrowsConfigErrorButNotNoConfigFound(): void
    {
        try {
            $this->resolver()->resolve(configFile: $this->work.'/missing.yaml');
            self::fail('Expected ConfigException');
        } catch (ConfigException $e) {
            self::assertNotInstanceOf(NoConfigFoundException::class, $e);
            self::assertStringContainsString('not found', $e->getMessage());
        }
    }

    #[Test]
    public function resolvesProjectConfigByNameWithMergedDefaultsAndHostEndpoint(): void
    {
        $this->writeGlobal('hosts.yaml', "live:\n  host: live.example.com\n  user: deploy\n");
        $this->writeGlobal('defaults.yaml', "verbose: true\ntarget:\n  dump_dir: /tmp/global/\n");
        $this->writeProject('defaults.yaml', "yes: true\n");
        $this->writeProject('prod.yaml', "origin: live\ntarget:\n  path: /var/www\n  dump_dir: /tmp/project/\n");

        $resolved = $this->resolver()->resolve(origin: 'prod');

        self::assertStringContainsString('project config: prod', $resolved->source);
        // origin endpoint string "live" resolved against hosts.yaml
        self::assertSame('live.example.com', $resolved->originConfig['host']);
        self::assertSame('deploy', $resolved->originConfig['user']);
        // merged: global verbose + project yes, project target.dump_dir overrides global
        self::assertTrue($resolved->mergedConfig['verbose']);
        self::assertTrue($resolved->mergedConfig['yes']);
        self::assertSame('/tmp/project/', $resolved->mergedConfig['target']['dump_dir']);
    }

    #[Test]
    public function resolvesHostReferences(): void
    {
        $this->writeGlobal('hosts.yaml', "a:\n  host: a.example.com\n  user: u\nb:\n  host: b.example.com\n  user: u\n");

        $resolved = $this->resolver()->resolve(origin: 'a', target: 'b');

        self::assertSame('a.example.com', $resolved->originConfig['host']);
        self::assertSame('b.example.com', $resolved->targetConfig['host']);
        self::assertStringContainsString('host references', $resolved->source);
    }

    #[Test]
    public function unknownHostThrowsConfigErrorButNotNoConfigFound(): void
    {
        $this->writeGlobal('hosts.yaml', "a:\n  host: a.example.com\n");

        try {
            $this->resolver()->resolve(origin: 'a', target: 'nope');
            self::fail('Expected ConfigException');
        } catch (ConfigException $e) {
            self::assertNotInstanceOf(NoConfigFoundException::class, $e);
            self::assertStringContainsString("Host 'nope' not found", $e->getMessage());
        }
    }

    #[Test]
    public function nothingProvidedThrowsNoConfigFound(): void
    {
        $this->expectException(NoConfigFoundException::class);

        $this->resolver()->resolve();
    }

    #[Test]
    public function listsReplaceAndAreNotMergedDuringDeepMerge(): void
    {
        $this->writeGlobal('defaults.yaml', "ignore_table:\n  - cache\n  - sessions\n");
        $this->writeProject('prod.yaml', "ignore_table:\n  - only_this\n");

        $resolved = $this->resolver()->resolve(origin: 'prod');

        self::assertSame(['only_this'], $resolved->mergedConfig['ignore_table']);
    }

    #[Test]
    public function discoversProjectDirInParentDirectory(): void
    {
        $nested = $this->work.'/a/b/c';
        mkdir($nested, 0o777, true);
        $this->writeProject('prod.yaml', "target:\n  path: /var/www\n");

        $resolver = new ConfigResolver(homeDir: $this->home, workingDir: $nested);
        $resolved = $resolver->resolve(origin: 'prod');

        self::assertStringContainsString('project config: prod', $resolved->source);
    }

    #[Test]
    public function mergesAdditionalHostFileForReferences(): void
    {
        $hostFile = $this->work.'/extra-hosts.yaml';
        file_put_contents($hostFile, "x:\n  host: x.example.com\n  user: u\ny:\n  host: y.example.com\n  user: u\n");

        $resolved = $this->resolver()->resolve(origin: 'x', target: 'y', hostFile: $hostFile);

        self::assertSame('x.example.com', $resolved->originConfig['host']);
        self::assertSame('y.example.com', $resolved->targetConfig['host']);
    }

    #[Test]
    public function missingHostFileThrowsConfigError(): void
    {
        $this->expectException(ConfigException::class);

        $this->resolver()->resolve(origin: 'x', target: 'y', hostFile: $this->work.'/nope.yaml');
    }

    #[Test]
    public function resolvesHostLinkToGlobalHost(): void
    {
        $this->writeGlobal('hosts.yaml', "prod:\n  host: prod.example.com\n  user: deploy\n");

        self::assertSame('prod.example.com', $this->resolver()->resolveHostLink('@prod')['host']);
    }

    #[Test]
    public function unknownHostLinkThrowsConfigError(): void
    {
        $this->expectException(ConfigException::class);

        $this->resolver()->resolveHostLink('@nope');
    }

    #[Test]
    public function resolvesProjectConfigWithInlineEndpoints(): void
    {
        $this->writeProject('prod.yaml', <<<'YAML'
            origin:
              host: o.example.com
              user: u
              db: {name: a, user: a, password: a}
            target:
              path: /var/www
              db: {name: b, user: r, password: r}
            YAML);

        $resolved = $this->resolver()->resolve(origin: 'prod');

        self::assertSame('o.example.com', $resolved->originConfig['host']);
        self::assertStringContainsString('project config: prod', $resolved->source);
    }

    #[Test]
    public function throwsWhenOriginHostReferenceUnknown(): void
    {
        $this->writeGlobal('hosts.yaml', "known:\n  host: known.example.com\n  user: u\n");

        $this->expectException(ConfigException::class);

        $this->resolver()->resolve(origin: 'ghost', target: 'known');
    }

    #[Test]
    public function silentlySkipsBrokenProjectFile(): void
    {
        $this->writeProject('broken.yaml', "- not\n- a\n- mapping\n");

        self::assertArrayNotHasKey('broken', $this->resolver()->getProjectConfigs());
    }

    #[Test]
    public function exposesLoadedProjectConfigsAndGlobalHosts(): void
    {
        $this->writeGlobal('hosts.yaml', "live:\n  host: live.example.com\n  user: deploy\n");
        $this->writeProject('prod.yaml', "target:\n  path: /var/www\n");

        $resolver = $this->resolver();

        self::assertArrayHasKey('prod', $resolver->getProjectConfigs());
        self::assertArrayHasKey('live', $resolver->getGlobalHosts());
    }

    private function resolver(): ConfigResolver
    {
        return new ConfigResolver(homeDir: $this->home, workingDir: $this->work);
    }

    private function writeGlobal(string $name, string $contents): void
    {
        file_put_contents($this->home.'/.sync-tool/'.$name, $contents);
    }

    private function writeProject(string $name, string $contents): void
    {
        file_put_contents($this->work.'/.sync-tool/'.$name, $contents);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
