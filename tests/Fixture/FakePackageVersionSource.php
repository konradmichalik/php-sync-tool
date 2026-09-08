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

namespace KonradMichalik\SyncTool\Tests\Fixture;

use KonradMichalik\SyncTool\Update\PackageVersionSource;

/**
 * FakePackageVersionSource.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class FakePackageVersionSource implements PackageVersionSource
{
    /**
     * @param list<string> $versions
     */
    public function __construct(private array $versions = []) {}

    public function versions(string $package): array
    {
        return $this->versions;
    }
}
