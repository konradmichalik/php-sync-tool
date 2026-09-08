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

namespace KonradMichalik\SyncTool\Tests\Unit\Update;

use KonradMichalik\SyncTool\Tests\Fixture\FakePackageVersionSource;
use KonradMichalik\SyncTool\Update\UpdateChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UpdateCheckerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class UpdateCheckerTest extends TestCase
{
    #[Test]
    public function reportsTheHighestStableVersionWhenNewerThanCurrent(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource(['1.0.0', '1.2.0', '1.1.0']));

        self::assertSame('1.2.0', $checker->newerVersion('vendor/pkg', '1.0.0'));
    }

    #[Test]
    public function returnsNullWhenTheCurrentVersionIsAlreadyLatest(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource(['1.0.0', '1.2.0']));

        self::assertNull($checker->newerVersion('vendor/pkg', '1.2.0'));
    }

    #[Test]
    public function returnsNullWhenTheCurrentVersionIsNewerThanAnyRelease(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource(['1.0.0', '1.2.0']));

        self::assertNull($checker->newerVersion('vendor/pkg', '9.9.9'));
    }

    #[Test]
    public function ignoresDevAndPreReleaseVersionStrings(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource(['dev-main', '2.0.0-alpha', '2.0.0-beta1', '1.2.x-dev', '1.0.0']));

        self::assertNull($checker->newerVersion('vendor/pkg', '1.0.0'), 'only full stable releases count as an update');
    }

    #[Test]
    public function stripsALeadingVFromTaggedVersions(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource(['v1.0.0', 'v1.3.0']));

        self::assertSame('1.3.0', $checker->newerVersion('vendor/pkg', 'v1.0.0'));
    }

    /**
     * A source that returned nothing (offline, timed out, malformed response)
     * must never look like "you are behind" — it looks like "up to date".
     */
    #[Test]
    public function returnsNullWhenTheSourceHasNoVersions(): void
    {
        $checker = new UpdateChecker(new FakePackageVersionSource([]));

        self::assertNull($checker->newerVersion('vendor/pkg', '1.0.0'));
    }
}
