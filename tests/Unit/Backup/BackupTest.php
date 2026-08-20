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

namespace KonradMichalik\SyncTool\Tests\Unit\Backup;

use DateTimeImmutable;
use KonradMichalik\SyncTool\Backup\{DumpFileNamer, DumpManager};
use KonradMichalik\SyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * BackupTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class BackupTest extends TestCase
{
    #[Test]
    public function dumpFileNamerUsesTimestampByDefault(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(db: new DatabaseConfig(name: 'project')));
        $name = (new DumpFileNamer())->generate($config, new DateTimeImmutable('2026-06-29 12:37:00'));

        self::assertSame('sync-tool_project_2026-06-29_12-37-00.sql', $name);
    }

    /**
     * Two runs in the same minute must not land on one filename, otherwise the
     * second overwrites the first and retention treats them as one dump.
     */
    #[Test]
    public function dumpFileNamerSeparatesRunsWithinTheSameMinute(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(db: new DatabaseConfig(name: 'project')));
        $namer = new DumpFileNamer();

        self::assertNotSame(
            $namer->generate($config, new DateTimeImmutable('2026-06-29 12:37:04')),
            $namer->generate($config, new DateTimeImmutable('2026-06-29 12:37:41')),
        );
    }

    #[Test]
    public function generatedDumpsCarryThePrefixRetentionGlobsOn(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(db: new DatabaseConfig(name: 'project')));

        self::assertStringStartsWith(
            DumpFileNamer::PREFIX,
            (new DumpFileNamer())->generate($config, new DateTimeImmutable('2026-06-29 12:37:00')),
        );
    }

    #[Test]
    public function dumpFileNamerUsesCustomName(): void
    {
        $config = new SyncConfig(dumpName: 'my-backup');

        self::assertSame('my-backup.sql', (new DumpFileNamer())->generate($config));
    }

    #[Test]
    public function filesToRemoveKeepsNewestN(): void
    {
        $manager = new DumpManager();
        $files = ['new3.gz', 'new2.gz', 'new1.gz', 'old2.gz', 'old1.gz'];

        self::assertSame(['old2.gz', 'old1.gz'], $manager->filesToRemove($files, 3));
        self::assertSame([], $manager->filesToRemove($files, 10));
        self::assertSame($files, $manager->filesToRemove($files, 0));
    }

    #[Test]
    public function extractFilenameTakesLastToken(): void
    {
        self::assertSame('/tmp/_db_2026.sql.gz', (new DumpManager())->extractFilename('2026-06-29 12:37:00 /tmp/_db_2026.sql.gz'));
    }

    #[Test]
    public function listDumpsCommandUsesGnuOrBsdStat(): void
    {
        $manager = new DumpManager();

        self::assertStringContainsString('-c "%y %n"', $manager->listDumpsCommand('stat', 'sort', 'grep', '/tmp/*', false));
        self::assertStringContainsString('-f "%Sm %N"', $manager->listDumpsCommand('stat', 'sort', 'grep', '/tmp/*', true));
    }
}
