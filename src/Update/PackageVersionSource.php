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

namespace KonradMichalik\SyncTool\Update;

/**
 * PackageVersionSource.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface PackageVersionSource
{
    /**
     * The versions a package has released, in whatever form the source names
     * them (tags, branch aliases, ...). An empty list stands for "unknown"
     * just as much as for "none released" — a source that cannot reach the
     * network returns one rather than throwing.
     *
     * @return list<string>
     */
    public function versions(string $package): array;
}
