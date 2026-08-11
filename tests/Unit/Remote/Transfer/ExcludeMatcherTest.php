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

    #[Test]
    public function matchesAcrossDirectorySeparatorsLikeRsyncsDefaultExclude(): void
    {
        self::assertTrue(ExcludeMatcher::matches('nested/deep/app.log', ['*.log']));
    }

    #[Test]
    public function isPathExcludedPrunesEverythingUnderAnExcludedDirectory(): void
    {
        self::assertTrue(ExcludeMatcher::isPathExcluded('_processed_', ['_processed_']));
        self::assertTrue(ExcludeMatcher::isPathExcluded('_processed_/image1.jpg', ['_processed_']));
        self::assertTrue(ExcludeMatcher::isPathExcluded('a/_processed_/nested/image1.jpg', ['_processed_']));
        self::assertFalse(ExcludeMatcher::isPathExcluded('kept/image1.jpg', ['_processed_']));
    }
}
