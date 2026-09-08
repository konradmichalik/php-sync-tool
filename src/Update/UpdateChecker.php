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

use function ltrim;
use function preg_match;
use function version_compare;

/**
 * UpdateChecker.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class UpdateChecker
{
    public function __construct(
        private PackageVersionSource $source = new PackagistVersionSource(),
    ) {}

    /**
     * The highest stable release newer than `$currentVersion`, or null when
     * there is none, the source came back empty, or every candidate is a
     * dev/alpha/beta/RC build. A source with nothing to report reads as
     * "up to date", never as an update.
     */
    public function newerVersion(string $package, string $currentVersion): ?string
    {
        $latest = null;

        foreach ($this->source->versions($package) as $version) {
            $normalized = ltrim($version, 'vV');

            if (!preg_match('/^\d+\.\d+\.\d+$/', $normalized)) {
                continue;
            }

            if (null === $latest || version_compare($normalized, $latest, '>')) {
                $latest = $normalized;
            }
        }

        if (null === $latest || version_compare($latest, ltrim($currentVersion, 'vV'), '<=')) {
            return null;
        }

        return $latest;
    }
}
