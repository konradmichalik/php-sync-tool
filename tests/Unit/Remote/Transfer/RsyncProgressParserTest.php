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

use KonradMichalik\SyncTool\Remote\Transfer\RsyncProgressParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RsyncProgressParserTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncProgressParserTest extends TestCase
{
    #[Test]
    public function readsThePercentFromAProgress2Line(): void
    {
        $parser = new RsyncProgressParser();

        self::assertSame(45.0, $parser->feed("      1,234,567  45%   12.34MB/s    0:00:03\r"));
    }

    #[Test]
    public function readsTheLastPercentWhenAChunkCarriesSeveralUpdates(): void
    {
        $parser = new RsyncProgressParser();

        self::assertSame(72.0, $parser->feed("  1,000  10%  1MB/s\r  7,200  72%  2MB/s\r"));
    }

    #[Test]
    public function readsAPercentThatIsSplitAcrossTwoChunks(): void
    {
        $parser = new RsyncProgressParser();

        self::assertNull($parser->feed('      1,234,567  4'));
        self::assertSame(45.0, $parser->feed("5%   12.34MB/s    0:00:03\r"));
    }

    #[Test]
    public function returnsNullForOutputWithoutAPercent(): void
    {
        $parser = new RsyncProgressParser();

        self::assertNull($parser->feed("sending incremental file list\n"));
    }
}
