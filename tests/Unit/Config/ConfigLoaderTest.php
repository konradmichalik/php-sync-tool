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

use KonradMichalik\SyncTool\Config\ConfigLoader;
use KonradMichalik\SyncTool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ConfigLoaderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigLoaderTest extends TestCase
{
    private string $dir;
    private ConfigLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new ConfigLoader();
        $dir = sys_get_temp_dir().'/db-sync-loader-'.uniqid();
        mkdir($dir);
        $this->dir = $dir;
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    #[Test]
    public function loadsYamlMapping(): void
    {
        $path = $this->write('config.yaml', "origin:\n  host: origin.example.com\ntarget:\n  path: /var/www\n");

        $data = $this->loader->load($path);

        self::assertSame('origin.example.com', $data['origin']['host']);
        self::assertSame('/var/www', $data['target']['path']);
    }

    #[Test]
    public function loadsJsonMapping(): void
    {
        $path = $this->write('config.json', '{"origin": {"host": "origin.example.com"}}');

        $data = $this->loader->load($path);

        self::assertSame('origin.example.com', $data['origin']['host']);
    }

    #[Test]
    public function autoDetectsJsonWithoutExtension(): void
    {
        $path = $this->write('config', '{"type": "TYPO3"}');

        self::assertSame('TYPO3', $this->loader->load($path)['type']);
    }

    #[Test]
    public function throwsWhenFileMissing(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('not found');

        $this->loader->load($this->dir.'/missing.yaml');
    }

    #[Test]
    public function throwsOnInvalidJson(): void
    {
        $path = $this->write('broken.json', '{invalid');

        $this->expectException(ConfigException::class);

        $this->loader->load($path);
    }

    #[Test]
    public function throwsWhenTopLevelIsNotAMapping(): void
    {
        $path = $this->write('list.yaml', "- one\n- two\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mapping');

        $this->loader->load($path);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
