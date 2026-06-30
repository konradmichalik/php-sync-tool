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

namespace KonradMichalik\SyncTool\Recipe;

use KonradMichalik\SyncTool\Exception\ValidationException;

use function sprintf;

/**
 * CredentialValidator.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class CredentialValidator
{
    private const REQUIRED = ['name', 'host', 'password', 'user'];

    /**
     * @param array<string, mixed> $db
     *
     * @throws ValidationException
     */
    public function validate(string $client, array $db): void
    {
        foreach (self::REQUIRED as $key) {
            $value = $db[$key] ?? null;

            if (null === $value || '' === $value) {
                throw new ValidationException(sprintf('Missing database credential "%s" for %s client', $key, $client));
            }
        }
    }
}
