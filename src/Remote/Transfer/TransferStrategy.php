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

namespace KonradMichalik\SyncTool\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;

/**
 * TransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
interface TransferStrategy
{
    public function transfer(SyncConfig $config, TransferPayload $payload): void;

    /**
     * A suffix appended directly after "Transferring dump"/"Transferring
     * files" in the console log — empty string for the default mechanism,
     * a leading-space-prefixed clause otherwise (e.g. " via SFTP").
     */
    public function describe(): string;
}
