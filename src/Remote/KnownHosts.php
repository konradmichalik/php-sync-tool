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

use function count;
use function in_array;
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
        $path = '' !== $this->path ? $this->path : (getenv('HOME') ?: '').'/.ssh/known_hosts';
        if (!is_file($path)) {
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

        $token = 22 === $port ? $host : sprintf('[%s]:%d', $host, $port);
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
