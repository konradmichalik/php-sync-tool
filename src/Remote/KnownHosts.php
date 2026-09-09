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

namespace KonradMichalik\SyncTool\Remote;

use KonradMichalik\SyncTool\Exception\SyncException;

use function count;
use function dirname;
use function in_array;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_starts_with;

/**
 * KnownHosts.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class KnownHosts
{
    public function __construct(private string $path = '') {}

    public function match(string $host, int $port, string $serverKey): HostKeyStatus
    {
        $path = $this->resolvePath();
        if (null === $path || !is_file($path)) {
            return HostKeyStatus::Unknown;
        }
        $lines = file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            return HostKeyStatus::Unknown;
        }

        [$serverType, $serverBlob] = $this->splitKey($serverKey);
        if (null === $serverBlob) {
            return HostKeyStatus::Unknown;
        }

        $token = $this->token($host, $port);
        $sameTypeSeen = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }
            $fields = preg_split('/\s+/', $line);
            if (false === $fields) {
                continue;
            }
            $hostField = $fields[0];
            $entryType = $fields[1] ?? '';
            $entryBlob = $fields[2] ?? '';
            if ('' === $entryBlob || str_starts_with($hostField, '@')) {
                continue;
            }
            if (!$this->hostMatches($hostField, $token)) {
                continue;
            }
            if ($entryBlob === $serverBlob) {
                return HostKeyStatus::Matched;
            }
            if ($this->normalizeType($entryType) === $this->normalizeType($serverType)) {
                $sameTypeSeen = true;
            }
        }

        return $sameTypeSeen ? HostKeyStatus::Mismatch : HostKeyStatus::Unknown;
    }

    public function append(string $host, int $port, string $serverKey): void
    {
        [$type, $blob] = $this->splitKey($serverKey);
        if (null === $blob) {
            return;
        }

        $path = $this->resolvePath();
        if (null === $path) {
            throw new SyncException(sprintf('Could not determine the known_hosts path for %s: $HOME is not set.', $host));
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new SyncException(sprintf('Could not create %s to store the trusted host key for %s.', $dir, $host));
        }

        $needsLeadingNewline = is_file($path) && filesize($path) > 0 && "\n" !== $this->lastByte($path);
        $line = ($needsLeadingNewline ? "\n" : '').sprintf('%s %s %s'."\n", $this->token($host, $port), $this->normalizeType($type), $blob);
        $isNewFile = !is_file($path);

        if (false === @file_put_contents($path, $line, \FILE_APPEND | \LOCK_EX)) {
            throw new SyncException(sprintf('Could not write the trusted host key for %s to %s.', $host, $path));
        }

        if ($isNewFile) {
            chmod($path, 0600);
        }
    }

    private function resolvePath(): ?string
    {
        if ('' !== $this->path) {
            return $this->path;
        }

        $home = getenv('HOME');
        if (false === $home || '' === $home) {
            return null;
        }

        return $home.'/.ssh/known_hosts';
    }

    private function token(string $host, int $port): string
    {
        return 22 === $port ? $host : sprintf('[%s]:%d', $host, $port);
    }

    private function lastByte(string $path): string
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            return '';
        }

        fseek($handle, -1, \SEEK_END);
        $byte = fread($handle, 1);
        fclose($handle);

        return false !== $byte ? $byte : '';
    }

    private function hostMatches(string $hostField, string $token): bool
    {
        foreach (explode(',', $hostField) as $candidate) {
            if (str_starts_with($candidate, '|1|')) {
                $parts = explode('|', $candidate);
                if (4 !== count($parts)) {
                    continue;
                }
                $salt = base64_decode($parts[2], true);
                if (false === $salt) {
                    continue;
                }
                if (hash_equals($parts[3], base64_encode(hash_hmac('sha1', $token, $salt, true)))) {
                    return true;
                }

                continue;
            }
            if ($candidate === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitKey(string $key): array
    {
        $parts = preg_split('/\s+/', trim($key));
        if (false === $parts || count($parts) < 2) {
            return ['', null];
        }

        return [$parts[0], $parts[1]];
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['ssh-rsa', 'rsa-sha2-256', 'rsa-sha2-512'], true) ? 'ssh-rsa' : $type;
    }
}
