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

namespace MoveElevator\DbSyncTool\Remote;

use MoveElevator\DbSyncTool\Config\ClientConfig;

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

    public function authorization(ClientConfig $client, bool $useSshpass): string
    {
        $port = $client->port;

        if (null !== $client->sshKey) {
            return sprintf('-e "ssh -i %s -p%d"', $client->sshKey, $port);
        }

        if ($useSshpass && '' !== $this->passwordEnvironment($client, $useSshpass)) {
            return sprintf('--rsh="sshpass -e ssh -p%d -o StrictHostKeyChecking=no -l %s"', $port, $client->user);
        }

        return sprintf('-e "ssh -p%d -o StrictHostKeyChecking=no"', $port);
    }

    public function userHost(ClientConfig $client): string
    {
        if ($client->isRemote()) {
            return sprintf('%s@%s', $client->user, $client->host);
        }

        return '';
    }

    public function options(?string $additionalOptions): string
    {
        $options = implode(' ', self::DEFAULT_OPTIONS);

        if (null !== $additionalOptions) {
            $options .= $additionalOptions;
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
