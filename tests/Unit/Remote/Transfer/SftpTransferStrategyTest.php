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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\Transfer\{SftpTransferStrategy, TransferPayload};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SftpTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SftpTransferStrategyTest extends TestCase
{
    #[Test]
    public function remoteToRemoteIsRejected(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $this->expectException(SyncException::class);
        (new SftpTransferStrategy())->transfer($config, new TransferPayload('/o/dump.gz', '/t/dump.gz'));
    }

    #[Test]
    public function describeMentionsSftp(): void
    {
        self::assertSame(' via SFTP', (new SftpTransferStrategy())->describe());
    }

    #[Test]
    public function remoteToRemoteIsRejectedEvenForDirectoryPaths(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);

        $this->expectException(SyncException::class);
        (new SftpTransferStrategy())->transfer($config, new TransferPayload('/srv/app/fileadmin', '/srv/web/fileadmin', ['*.log']));
    }
}
