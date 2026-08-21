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

/**
 * ClientConfig.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ClientConfig
{
    /**
     * @param list<string>            $postSql
     * @param array<string, string>   $console
     * @param array<string, string>   $scripts
     * @param list<AnonymizationRule> $anonymize
     */
    public function __construct(
        public string $path = '',
        public string $name = '',
        public string $host = '',
        public string $user = '',
        public ?string $password = null,
        public ?string $sshKey = null,
        public int $port = 22,
        public string $dumpDir = '/tmp/',
        public ?int $keepDumps = null,
        public DatabaseConfig $db = new DatabaseConfig(),
        public ?JumpHostConfig $jumpHost = null,
        public ?string $afterDump = null,
        public array $postSql = [],
        public array $console = [],
        public array $scripts = [],
        public bool $protect = false,
        public string $link = '',
        public array $anonymize = [],
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
            path: ConfigAccessor::getString($data, 'path', ''),
            name: ConfigAccessor::getString($data, 'name', ''),
            host: ConfigAccessor::getString($data, 'host', ''),
            user: ConfigAccessor::getString($data, 'user', ''),
            password: ConfigAccessor::getStringOrNull($data, 'password'),
            sshKey: ConfigAccessor::getStringOrNull($data, 'ssh_key'),
            port: ConfigAccessor::getInt($data, 'port', 22),
            dumpDir: ConfigAccessor::getString($data, 'dump_dir', '/tmp/'),
            keepDumps: ConfigAccessor::getIntOrNull($data, 'keep_dumps'),
            db: DatabaseConfig::fromArray(self::subArray($data, 'db')),
            jumpHost: JumpHostConfig::fromArray(self::subArray($data, 'jump_host')),
            afterDump: ConfigAccessor::getStringOrNull($data, 'after_dump'),
            postSql: ConfigAccessor::getStringList($data, 'post_sql'),
            console: ConfigAccessor::getStringMap($data, 'console'),
            // `script` is what the documentation has always shown; `scripts` is what
            // the code read, so every documented lifecycle block was ignored.
            scripts: ConfigAccessor::getStringMap($data, 'scripts', 'script'),
            protect: ConfigAccessor::getBool($data, 'protect', false),
            link: ConfigAccessor::getString($data, 'link', ''),
            anonymize: AnonymizationRule::fromConfig(self::subArray($data, 'anonymize')),
        );
    }

    public function withDb(DatabaseConfig $db): self
    {
        return new self(
            path: $this->path,
            name: $this->name,
            host: $this->host,
            user: $this->user,
            password: $this->password,
            sshKey: $this->sshKey,
            port: $this->port,
            dumpDir: $this->dumpDir,
            keepDumps: $this->keepDumps,
            db: $db,
            jumpHost: $this->jumpHost,
            afterDump: $this->afterDump,
            postSql: $this->postSql,
            console: $this->console,
            scripts: $this->scripts,
            protect: $this->protect,
            link: $this->link,
            anonymize: $this->anonymize,
        );
    }

    public function isRemote(): bool
    {
        return '' !== $this->host;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private static function subArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }
}
