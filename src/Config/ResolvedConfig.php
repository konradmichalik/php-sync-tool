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

namespace KonradMichalik\SyncTool\Config;

/**
 * ResolvedConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ResolvedConfig
{
    /**
     * @param array<string, mixed> $originConfig
     * @param array<string, mixed> $targetConfig
     * @param array<string, mixed> $mergedConfig
     */
    public function __construct(
        public ?string $configFile = null,
        public array $originConfig = [],
        public array $targetConfig = [],
        public array $mergedConfig = [],
        public string $source = '',
    ) {}
}
