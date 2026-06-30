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

namespace KonradMichalik\SyncTool\Security;

/**
 * LogSanitizer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LogSanitizer
{
    /**
     * Ordered (pattern, replacement) pairs masking credentials in commands
     * before they reach logs or verbose output. Order is significant and
     * mirrors Python's sanitize_command_for_logging() exactly.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PATTERNS = [
        ["~-p'[^']*'~", "-p'***'"],
        ['~-p"[^"]*"~', '-p"***"'],
        ['~-p[^\s\'"]+~', '-p***'],
        ["~SSHPASS='[^']*'~", "SSHPASS='***'"],
        ['~SSHPASS="[^"]*"~', 'SSHPASS="***"'],
        ['~SSHPASS=[^\s]+~', 'SSHPASS=***'],
        ['~--defaults-file=[^\s]+~', '--defaults-file=***'],
        ['~--defaults-extra-file=[^\s]+~', '--defaults-extra-file=***'],
        ["~echo '[A-Za-z0-9+/=]{20,}' \\| base64~", "echo '***' | base64"],
    ];

    public static function sanitize(string $command): string
    {
        $sanitized = $command;

        foreach (self::PATTERNS as [$pattern, $replacement]) {
            $sanitized = preg_replace($pattern, $replacement, $sanitized) ?? $sanitized;
        }

        return $sanitized;
    }
}
