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

use KonradMichalik\SyncTool\Command\LegacyShortOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LegacyShortOptionsTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LegacyShortOptionsTest extends TestCase
{
    #[Test]
    public function rewritesDumpNameToItsLongForm(): void
    {
        self::assertSame(
            ['bin/sync-tool', '--dump-name', 'custom.sql'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-dn', 'custom.sql']),
        );
    }

    #[Test]
    public function rewritesKeepDumpWithADirectoryToTwoLongOptions(): void
    {
        self::assertSame(
            ['bin/sync-tool', '--keep-dump', '--target-dump-dir', '/tmp/dumps'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-kd', '/tmp/dumps']),
        );
    }

    #[Test]
    public function rewritesABareKeepDumpAtTheEndOfArgvWithoutADirectory(): void
    {
        self::assertSame(
            ['bin/sync-tool', '--keep-dump'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-kd']),
        );
    }

    /**
     * db-sync-tool's `-kd` takes an optional directory, so a following token
     * that looks like another option must not be swallowed as the value.
     */
    #[Test]
    public function rewritesKeepDumpWithoutSwallowingAFollowingOption(): void
    {
        self::assertSame(
            ['bin/sync-tool', '--keep-dump', '-y'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-kd', '-y']),
        );
    }

    #[Test]
    public function leavesEverythingElseUntouched(): void
    {
        self::assertSame(
            ['bin/sync-tool', 'sync', '-f', 'x.yaml', '-y', '--dry-run'],
            LegacyShortOptions::rewrite(['bin/sync-tool', 'sync', '-f', 'x.yaml', '-y', '--dry-run']),
        );
    }

    /**
     * The deployer-tools call this exists for: `db_sync_tool ... -kd $dir -dn $name`.
     */
    #[Test]
    public function rewritesTheDeployerToolsCallInOneGo(): void
    {
        self::assertSame(
            ['bin/sync-tool', '-f', 'x.yaml', '-y', '--keep-dump', '--target-dump-dir', '/tmp/dumps', '--dump-name', 'backup.sql'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-f', 'x.yaml', '-y', '-kd', '/tmp/dumps', '-dn', 'backup.sql']),
        );
    }

    /**
     * Symfony Console itself stops interpreting anything as an option once it
     * sees a bare `--` (ArgvInput::parseToken()). A literal `-dn`/`-kd` after
     * that point is a plain argument, same as any other flag-shaped value
     * would be, and must survive untouched rather than being rewritten.
     */
    #[Test]
    public function leavesTokensAfterTheEndOfOptionsDelimiterUntouched(): void
    {
        self::assertSame(
            ['bin/sync-tool', '-f', 'x.yaml', '--', '-dn', '-kd'],
            LegacyShortOptions::rewrite(['bin/sync-tool', '-f', 'x.yaml', '--', '-dn', '-kd']),
        );
    }
}
