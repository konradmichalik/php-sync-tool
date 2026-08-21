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

use KonradMichalik\SyncTool\Config\{ClientConfig, JumpHostConfig};
use KonradMichalik\SyncTool\Security\Shell;

use function implode;
use function ltrim;
use function sprintf;
use function str_replace;

/**
 * RsyncCommandBuilder.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncCommandBuilder
{
    /**
     * Directory trees: mirror the source, compress on the wire, normalise
     * filename encoding and set group-readable modes on dirs and files.
     *
     * @var list<string>
     */
    private const DIRECTORY_OPTIONS = [
        '--delete',
        '-a',
        '-z',
        '--stats',
        '--human-readable',
        '--iconv=UTF-8',
        '--chmod=D2770,F660',
    ];

    /**
     * A single gzip dump. `-z` would re-compress compressed bytes, and `--delete`
     * and `--iconv` have no meaning without a directory to walk. The restrictive
     * file mode stays: the dump holds production data.
     *
     * @var list<string>
     */
    private const SINGLE_FILE_OPTIONS = [
        '-a',
        '--stats',
        '--human-readable',
        '--chmod=F660',
    ];

    public function passwordEnvironment(ClientConfig $client, bool $useSshpass): string
    {
        if ($useSshpass && null === $client->sshKey && null !== $client->password && '' !== $client->password) {
            return 'SSHPASS='.Shell::quote($client->password).' ';
        }

        return '';
    }

    /**
     * The remote-shell option for rsync, as one shell-quoted argument.
     *
     * `$strictHostKeyChecking` mirrors the `ssh_strict_host_key_checking` config
     * key that the phpseclib command channel already honours. Hard-coding
     * `StrictHostKeyChecking=no` here left the data channel unauthenticated while
     * the tool documented the opposite.
     */
    public function authorization(
        ClientConfig $client,
        bool $useSshpass,
        ?JumpHostConfig $jump = null,
        bool $strictHostKeyChecking = true,
    ): string {
        // A configured key wins over sshpass, which is why passwordEnvironment()
        // returns nothing as soon as one is present.
        $withPassword = '' !== $this->passwordEnvironment($client, $useSshpass);

        $parts = $withPassword ? ['sshpass', '-e', 'ssh'] : ['ssh'];

        if (null !== $jump) {
            $parts[] = '-J';
            $parts[] = $jump->sshSpec();
        }

        if (null !== $client->sshKey) {
            $parts[] = '-i';
            $parts[] = $client->sshKey;
        }

        $parts[] = '-p'.$client->port;
        $parts[] = '-o';
        $parts[] = 'StrictHostKeyChecking='.($strictHostKeyChecking ? 'yes' : 'no');

        if ($withPassword) {
            $parts[] = '-l';
            $parts[] = $client->user;
        }

        return ($withPassword ? '--rsh=' : '-e ').Shell::quote(implode(' ', $parts));
    }

    public function userHost(ClientConfig $client): string
    {
        if ($client->isRemote()) {
            return sprintf('%s@%s', $client->user, $client->host);
        }

        return '';
    }

    /**
     * @param list<string> $excludePatterns
     */
    public function options(?string $additionalOptions, array $excludePatterns = [], bool $withProgress = false, bool $singleFile = false): string
    {
        $options = implode(' ', $singleFile ? self::SINGLE_FILE_OPTIONS : self::DIRECTORY_OPTIONS);

        if ($withProgress) {
            $options .= ' --info=progress2 --no-i-r';
        }

        foreach ($excludePatterns as $pattern) {
            $options .= sprintf(" --exclude='%s'", str_replace("'", "'\\''", $pattern));
        }

        if (null !== $additionalOptions && '' !== ltrim($additionalOptions)) {
            $options .= ' '.ltrim($additionalOptions);
        }

        return $options;
    }

    public function build(
        string $passwordEnvironment,
        string $options,
        string $authorization,
        string $originUserHost,
        string $originPath,
        string $targetUserHost,
        string $targetPath,
    ): string {
        $origin = '' !== $originUserHost ? $originUserHost.':' : '';
        $target = '' !== $targetUserHost ? $targetUserHost.':' : '';

        // Both operands are one shell argument each. Interpolating them raw made a
        // configured path carrying shell syntax a command-execution path on the
        // machine driving the sync. Ordinary paths contain nothing that needs
        // quoting, so they still appear verbatim. What `rsync` then does with a
        // remote path on the far side is its own business.
        return sprintf(
            '%srsync %s %s %s %s',
            $passwordEnvironment,
            $options,
            $authorization,
            Shell::quote($origin.$originPath),
            Shell::quote($target.$targetPath),
        );
    }
}
