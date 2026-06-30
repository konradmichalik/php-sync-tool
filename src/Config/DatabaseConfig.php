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
 * DatabaseConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class DatabaseConfig
{
    public function __construct(
        public string $name = '',
        public string $host = '',
        public string $user = '',
        public string $password = '',
        public int $port = 0,
        public bool $sslDisabled = false,
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
            name: ConfigAccessor::getString($data, 'name', ''),
            host: ConfigAccessor::getString($data, 'host', ''),
            user: ConfigAccessor::getString($data, 'user', ''),
            password: ConfigAccessor::getString($data, 'password', ''),
            port: ConfigAccessor::getInt($data, 'port', 0),
            sslDisabled: ConfigAccessor::getBool($data, 'ssl_disabled', false),
        );
    }
}
