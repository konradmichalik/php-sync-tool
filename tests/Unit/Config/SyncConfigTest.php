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

use KonradMichalik\SyncTool\Config\SyncConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SyncConfigTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncConfigTest extends TestCase
{
    #[Test]
    public function defaultsMatchPython(): void
    {
        $config = SyncConfig::fromArray([]);

        self::assertTrue($config->checkDump);
        self::assertTrue($config->useRsync);
        self::assertTrue($config->defaultOriginDumpDir);
        self::assertFalse($config->verbose);
        self::assertFalse($config->withFiles);
        self::assertSame('', $config->dumpName);
        self::assertSame([], $config->files);
        self::assertSame(22, $config->origin->port);
    }

    #[Test]
    public function importFileMapsFromImportKey(): void
    {
        $config = SyncConfig::fromArray(['import' => '/tmp/dump.sql.gz']);

        self::assertSame('/tmp/dump.sql.gz', $config->importFile);
    }

    #[Test]
    public function sshPasswordIsReadFromNestedDict(): void
    {
        $config = SyncConfig::fromArray([
            'ssh_password' => ['origin' => 'o-secret', 'target' => 't-secret'],
        ]);

        self::assertSame('o-secret', $config->sshPasswordOrigin);
        self::assertSame('t-secret', $config->sshPasswordTarget);
    }

    #[Test]
    public function ignoreTablesFallBackToSingularKey(): void
    {
        $config = SyncConfig::fromArray(['ignore_table' => ['cache', 'sessions']]);

        self::assertSame(['cache', 'sessions'], $config->ignoreTables);
    }

    #[Test]
    public function filesNewFlatListFormat(): void
    {
        $config = SyncConfig::fromArray([
            'files' => [
                ['origin' => 'fileadmin/', 'target' => 'fileadmin/', 'exclude' => ['*.log']],
            ],
        ]);

        self::assertCount(1, $config->files);
        self::assertSame('fileadmin/', $config->files[0]->origin);
        self::assertSame(['*.log'], $config->files[0]->exclude);
    }

    #[Test]
    public function filesLegacyNestedFormat(): void
    {
        $config = SyncConfig::fromArray([
            'files' => [
                'config' => [
                    ['origin' => 'fileadmin/', 'target' => 'fileadmin/'],
                ],
                'option' => ['--verbose', '--compress'],
            ],
        ]);

        self::assertCount(1, $config->files);
        self::assertSame('fileadmin/', $config->files[0]->origin);
        self::assertSame('--verbose --compress', $config->filesOptions);
    }

    #[Test]
    public function directFilesOptionsTakePrecedenceOverLegacy(): void
    {
        $config = SyncConfig::fromArray([
            'files' => ['option' => ['--legacy']],
            'files_options' => '--direct',
        ]);

        self::assertSame('--direct', $config->filesOptions);
    }

    #[Test]
    public function getClientReturnsOriginAndTarget(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'origin.example.com'],
            'target' => ['host' => 'target.example.com'],
        ]);

        self::assertSame('origin.example.com', $config->getClient('origin')->host);
        self::assertSame('target.example.com', $config->getClient('target')->host);
    }

    #[Test]
    public function strictHostKeyCheckingDefaultsTrueAndReadsFlag(): void
    {
        self::assertTrue(SyncConfig::fromArray([])->strictHostKeyChecking);
        self::assertFalse(SyncConfig::fromArray(['ssh_strict_host_key_checking' => false])->strictHostKeyChecking);
    }
}
