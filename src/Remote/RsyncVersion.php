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

namespace KonradMichalik\SyncTool\Remote;

use function preg_match;
use function version_compare;

/**
 * RsyncVersion.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncVersion
{
    /**
     * The local rsync does not change while a sync runs, but every transferred
     * entry used to ask it for its version again — one process per file entry.
     */
    private bool $probed = false;

    private ?string $version = null;

    /**
     * Whether a local rsync exists at all. A machine without one, a slim container
     * most often, cannot run any rsync-based transfer, and the caller has to pick
     * another way of moving the dump rather than let the command fail.
     */
    public function isAvailable(CommandRunner $local): bool
    {
        return null !== $this->version($local);
    }

    /**
     * `--info=progress2` arrived in rsync 3.1.0. Older builds abort on the unknown
     * option, and macOS still ships 2.6.9, so the version decides whether a
     * transfer can report a percentage at all.
     */
    public function supportsProgress2(CommandRunner $local): bool
    {
        $version = $this->version($local);

        return null !== $version && version_compare($version, '3.1.0', '>=');
    }

    /**
     * Null when rsync is absent, or present but too odd to name a version. Both
     * cases rule out the options that depend on one.
     */
    private function version(CommandRunner $local): ?string
    {
        if ($this->probed) {
            return $this->version;
        }

        $this->probed = true;
        $output = $local->run('rsync --version', true);

        if (1 === preg_match('/version\s+(\d+(?:\.\d+)+)/', $output, $matches)) {
            $this->version = $matches[1];
        }

        return $this->version;
    }
}
