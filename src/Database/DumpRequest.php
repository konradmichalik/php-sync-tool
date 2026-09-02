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

namespace KonradMichalik\SyncTool\Database;

use KonradMichalik\SyncTool\Config\DatabaseConfig;

/**
 * DumpRequest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class DumpRequest
{
    /**
     * @param list<string> $exportTables tables to dump exclusively, already sanitized
     * @param list<string> $ignoreTables tables to skip, wildcards already expanded
     */
    public function __construct(
        public DatabaseConfig $db,
        public string $credentialsPath,
        public string $dumpFilePath,
        public array $exportTables = [],
        public array $ignoreTables = [],
        public string $where = '',
        public string $additionalOptions = '',
        public ?string $serverVersion = null,
    ) {}
}
