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

use function sprintf;

/**
 * RsyncCommandBuilder.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RsyncCommandBuilder
{
    /**
     * @var list<string>
     */
    private const DEFAULT_OPTIONS = [
        '--delete',
        '-a',
        '-z',
        '--stats',
        '--human-readable',
        '--iconv=UTF-8',
        '--chmod=D2770,F660',
    ];

    public function passwordEnvironment(ClientConfig $client, bool $useSshpass): string
    {
        if ($useSshpass && null === $client->sshKey && null !== $client->password && '' !== $client->password) {
            return sprintf("SSHPASS='%s' ", $client->password);
        }

        return '';
    }

    public function authorization(ClientConfig $client, bool $useSshpass, ?JumpHostConfig $jump = null): string
    {
        $port = $client->port;
        $jumpOpt = null !== $jump ? ' -J '.$jump->sshSpec() : '';

        if (null !== $client->sshKey) {
            return sprintf('-e "ssh%s -i %s -p%d"', $jumpOpt, $client->sshKey, $port);
        }

        if ($useSshpass && '' !== $this->passwordEnvironment($client, $useSshpass)) {
            return sprintf('--rsh="sshpass -e ssh%s -p%d -o StrictHostKeyChecking=no -l %s"', $jumpOpt, $port, $client->user);
        }

        return sprintf('-e "ssh%s -p%d -o StrictHostKeyChecking=no"', $jumpOpt, $port);
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
    public function options(?string $additionalOptions, array $excludePatterns = []): string
    {
        $options = implode(' ', self::DEFAULT_OPTIONS);

        foreach ($excludePatterns as $pattern) {
            $options .= sprintf(" --exclude='%s'", $pattern);
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

        return sprintf(
            '%srsync %s %s %s%s %s%s',
            $passwordEnvironment,
            $options,
            $authorization,
            $origin,
            $originPath,
            $target,
            $targetPath,
        );
    }
}
