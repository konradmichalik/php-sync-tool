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

use KonradMichalik\SyncTool\Remote\SshAgent;

/**
 * FakeSshAgent.
 *
 * The real one answers from the developer's own socket, which would make every
 * test about agent handling pass or fail depending on whose machine it runs on.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class FakeSshAgent extends SshAgent
{
    public int $probes = 0;

    public function __construct(private readonly bool $hasKeys = false) {}

    public function hasKeys(): bool
    {
        ++$this->probes;

        return $this->hasKeys;
    }
}
