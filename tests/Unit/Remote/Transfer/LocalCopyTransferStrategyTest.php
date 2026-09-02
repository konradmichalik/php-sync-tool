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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\Transfer\{LocalCopyTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LocalCopyTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LocalCopyTransferStrategyTest extends TestCase
{
    #[Test]
    public function copiesTheDumpBetweenTwoLocalPaths(): void
    {
        $recorder = new RecordingCommandRunner();

        $this->strategy($recorder)->transfer(
            SyncConfig::fromArray([]),
            new TransferPayload('/srv/a/dump.sql.gz', '/srv/b/dump.sql.gz', singleFile: true),
        );

        self::assertTrue($recorder->ran("cp -- '/srv/a/dump.sql.gz' '/srv/b/dump.sql.gz'"));
    }

    /**
     * Without `--`, a path starting with `-` would be parsed by `cp` itself as
     * an option rather than an operand, `escapeshellarg()` only protects against
     * the shell, not against `cp`'s own argument parsing.
     */
    #[Test]
    public function separatesOptionsFromPathsStartingWithADash(): void
    {
        $recorder = new RecordingCommandRunner();

        $this->strategy($recorder)->transfer(
            SyncConfig::fromArray([]),
            new TransferPayload('-rf.sql.gz', '/srv/b/dump.sql.gz', singleFile: true),
        );

        self::assertTrue($recorder->ran("cp -- '-rf.sql.gz' '/srv/b/dump.sql.gz'"));
    }

    /**
     * `cp` has no `--delete` and no excludes, so copying a tree would put back
     * exactly the files the configuration asks to leave out.
     */
    #[Test]
    public function refusesADirectoryInsteadOfSynchronizingTheWrongTree(): void
    {
        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('needs rsync');

        $this->strategy(new RecordingCommandRunner())->transfer(
            SyncConfig::fromArray([]),
            new TransferPayload('/srv/a/fileadmin/', '/srv/b/fileadmin/', excludePatterns: ['*.log']),
        );
    }

    private function strategy(RecordingCommandRunner $recorder): LocalCopyTransferStrategy
    {
        return new LocalCopyTransferStrategy(new FakeRunnerFactory($recorder));
    }
}
