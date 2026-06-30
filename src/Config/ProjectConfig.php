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

namespace MoveElevator\DbSyncTool\Config;

/**
 * ProjectConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProjectConfig
{
    /**
     * @param string|array<string, mixed>|null $origin
     * @param string|array<string, mixed>|null $target
     * @param array<string, mixed>             $config
     */
    public function __construct(
        public string $name,
        public string $filePath,
        public string|array|null $origin = null,
        public string|array|null $target = null,
        public array $config = [],
    ) {}
}
