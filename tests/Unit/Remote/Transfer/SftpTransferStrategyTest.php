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
use phpseclib3\Net\SFTP;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function sys_get_temp_dir;

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function escapingListingEntries(): iterable
    {
        yield 'parent segments' => ['../../x'];
        yield 'parent segment in the middle' => ['a/../../x'];
        yield 'current directory before parent segment' => ['./../x'];
        yield 'absolute path' => ['/etc/cron.d/x'];
    }

    #[Test]
    #[DataProvider('escapingListingEntries')]
    public function aListingEntryOutsideTheTargetIsNeverDownloaded(string $entry): void
    {
        $sftp = $this->createMock(SFTP::class);
        $sftp->method('nlist')->willReturn([$entry]);
        $sftp->method('is_dir')->willReturn(false);
        $sftp->expects(self::never())->method('get');

        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('outside the target directory');

        $this->downloadDirectory($sftp, '/srv/app', '/tmp/target');
    }

    #[Test]
    public function aFileNameContainingDotsIsDownloaded(): void
    {
        $target = sys_get_temp_dir();

        $sftp = $this->createMock(SFTP::class);
        $sftp->method('nlist')->willReturn(['archive..tar']);
        $sftp->method('is_dir')->willReturn(false);
        $sftp->expects(self::once())->method('get')->with('/srv/app/archive..tar', $target.'/archive..tar')->willReturn(true);

        $this->downloadDirectory($sftp, '/srv/app', $target);
    }

    private function downloadDirectory(SFTP $sftp, string $originDir, string $targetDir): void
    {
        (new ReflectionMethod(SftpTransferStrategy::class, 'downloadDirectory'))
            ->invoke(new SftpTransferStrategy(), $sftp, $originDir, $targetDir, []);
    }
}
