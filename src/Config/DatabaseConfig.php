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

use KonradMichalik\SyncTool\Enum\DatabaseSystem;

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
        public bool $sslSkipVerify = false,
        public ?string $sslCa = null,
        public ?string $sslCapath = null,
        public ?string $sslCert = null,
        public ?string $sslKey = null,
        public ?string $sslCipher = null,
        public DatabaseSystem $type = DatabaseSystem::MySQL,
    ) {}

    /**
     * Whether anything about TLS was configured at all. Used to refuse a run on a
     * system that cannot honour it rather than connecting in the clear.
     */
    public function hasTlsSettings(): bool
    {
        return $this->sslDisabled
            || $this->sslSkipVerify
            || null !== $this->sslCa
            || null !== $this->sslCapath
            || null !== $this->sslCert
            || null !== $this->sslKey
            || null !== $this->sslCipher;
    }

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
            sslSkipVerify: ConfigAccessor::getBool($data, 'ssl_skip_verify', false),
            sslCa: ConfigAccessor::getStringOrNull($data, 'ssl_ca'),
            sslCapath: ConfigAccessor::getStringOrNull($data, 'ssl_capath'),
            sslCert: ConfigAccessor::getStringOrNull($data, 'ssl_cert'),
            sslKey: ConfigAccessor::getStringOrNull($data, 'ssl_key'),
            sslCipher: ConfigAccessor::getStringOrNull($data, 'ssl_cipher'),
            type: DatabaseSystem::fromDriver(ConfigAccessor::getString($data, 'type', '')),
        );
    }
}
