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

namespace KonradMichalik\SyncTool\Update;

use Throwable;

use function array_keys;
use function file_get_contents;
use function is_array;
use function json_decode;
use function sprintf;
use function stream_context_create;

use const JSON_THROW_ON_ERROR;

/**
 * PackagistVersionSource.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class PackagistVersionSource implements PackageVersionSource
{
    /**
     * Long enough for a healthy connection, short enough that an offline or
     * CI environment never notices this ran at all.
     */
    private const TIMEOUT_SECONDS = 2;

    public function versions(string $package): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT_SECONDS,
                    'ignore_errors' => true,
                    'header' => 'Accept: application/json',
                ],
            ]);

            $json = @file_get_contents(
                sprintf('https://packagist.org/packages/%s.json', $package),
                false,
                $context,
            );

            if (false === $json) {
                return [];
            }

            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data) || !is_array($data['package'] ?? null) || !is_array($data['package']['versions'] ?? null)) {
                return [];
            }

            return array_keys($data['package']['versions']);
        } catch (Throwable) {
            // Offline, DNS failure, a timeout, or a response that isn't the
            // JSON we expect: all the same "we don't know" to the caller.
            return [];
        }
    }
}
