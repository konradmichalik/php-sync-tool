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

use KonradMichalik\SyncTool\Remote\Transfer\ExcludeMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ExcludeMatcherTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ExcludeMatcherTest extends TestCase
{
    #[Test]
    public function noPatternsNeverMatch(): void
    {
        self::assertFalse(ExcludeMatcher::matches('cache/entry.log', []));
    }

    #[Test]
    public function matchesAGlobPatternAgainstTheWholeRelativePath(): void
    {
        self::assertTrue(ExcludeMatcher::matches('app.log', ['*.log']));
        self::assertFalse(ExcludeMatcher::matches('app.txt', ['*.log']));
    }

    #[Test]
    public function matchesADirectoryPrefixPattern(): void
    {
        self::assertTrue(ExcludeMatcher::matches('cache/entry.log', ['cache/*']));
        self::assertFalse(ExcludeMatcher::matches('other/entry.log', ['cache/*']));
    }

    #[Test]
    public function anyMatchingPatternIsEnough(): void
    {
        self::assertTrue(ExcludeMatcher::matches('nested/app.log', ['*.txt', 'nested/*']));
    }
}
