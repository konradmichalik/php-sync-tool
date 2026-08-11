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

/**
 * TransferPayload.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class TransferPayload
{
    /**
     * @param list<string> $excludePatterns
     */
    public function __construct(
        public string $originPath,
        public string $targetPath,
        public array $excludePatterns = [],
        public ?string $extraRsyncOptions = null,
    ) {}
}
