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

use function is_array;
use function is_scalar;
use function sprintf;

/**
 * HostDefinition.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class HostDefinition
{
    /**
     * @param array<string, mixed> $db
     */
    public function __construct(
        public string $name,
        public ?string $host = null,
        public ?string $user = null,
        public ?string $path = null,
        public ?int $port = null,
        public ?string $sshKey = null,
        public bool $protect = false,
        public array $db = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $name, array $data): self
    {
        $port = $data['port'] ?? null;
        $db = $data['db'] ?? null;

        return new self(
            name: $name,
            host: self::nullableString($data, 'host'),
            user: self::nullableString($data, 'user'),
            path: self::nullableString($data, 'path'),
            port: is_numeric($port) ? (int) $port : null,
            sshKey: self::nullableString($data, 'ssh_key'),
            protect: (bool) ($data['protect'] ?? false),
            db: is_array($db) ? $db : [],
        );
    }

    public function isRemote(): bool
    {
        return null !== $this->host;
    }

    public function displayName(): string
    {
        return null !== $this->host
            ? sprintf('%s (%s)', $this->name, $this->host)
            : sprintf('%s (local)', $this->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientConfig(): array
    {
        $config = [];

        if (null !== $this->host && '' !== $this->host) {
            $config['host'] = $this->host;
        }
        if (null !== $this->user && '' !== $this->user) {
            $config['user'] = $this->user;
        }
        if (null !== $this->path && '' !== $this->path) {
            $config['path'] = $this->path;
        }
        if (null !== $this->port && 0 !== $this->port) {
            $config['port'] = $this->port;
        }
        if (null !== $this->sshKey && '' !== $this->sshKey) {
            $config['ssh_key'] = $this->sshKey;
        }
        if ([] !== $this->db) {
            $config['db'] = $this->db;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
