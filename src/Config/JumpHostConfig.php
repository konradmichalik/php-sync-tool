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
 * JumpHostConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class JumpHostConfig
{
    public function __construct(
        public string $host = '',
        public string $user = '',
        public ?string $password = null,
        public ?string $sshKey = null,
        public int $port = 22,
        public ?string $private = null,
        public ?string $name = null,
    ) {}

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if (null === $data || [] === $data) {
            return null;
        }

        return new self(
            host: ConfigAccessor::getString($data, 'host', ''),
            user: ConfigAccessor::getString($data, 'user', ''),
            password: ConfigAccessor::getStringOrNull($data, 'password'),
            sshKey: ConfigAccessor::getStringOrNull($data, 'ssh_key'),
            port: ConfigAccessor::getInt($data, 'port', 22),
            private: ConfigAccessor::getStringOrNull($data, 'private'),
            name: ConfigAccessor::getStringOrNull($data, 'name'),
        );
    }

    /**
     * SSH ProxyJump destination: [user@]host[:port] (port omitted when default 22).
     */
    public function sshSpec(): string
    {
        $spec = '' !== $this->user ? $this->user.'@'.$this->host : $this->host;

        if (22 !== $this->port) {
            $spec .= ':'.$this->port;
        }

        return $spec;
    }
}
