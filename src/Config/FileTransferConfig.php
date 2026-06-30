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
 * FileTransferConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final readonly class FileTransferConfig
{
    /**
     * @param list<string> $exclude
     */
    public function __construct(
        public string $origin = '',
        public string $target = '',
        public array $exclude = [],
        public ?string $options = null,
    ) {}

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        if (null === $data || [] === $data) {
            return new self();
        }

        return new self(
            origin: ConfigAccessor::getString($data, 'origin', ''),
            target: ConfigAccessor::getString($data, 'target', ''),
            exclude: ConfigAccessor::getStringList($data, 'exclude'),
            options: ConfigAccessor::getStringOrNull($data, 'options'),
        );
    }
}
