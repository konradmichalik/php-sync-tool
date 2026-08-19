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

use KonradMichalik\SyncTool\Remote\Transfer\TransferPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TransferPayloadTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class TransferPayloadTest extends TestCase
{
    #[Test]
    public function defaultsAreEmpty(): void
    {
        $payload = new TransferPayload('/o/dump.gz', '/t/dump.gz');

        self::assertSame('/o/dump.gz', $payload->originPath);
        self::assertSame('/t/dump.gz', $payload->targetPath);
        self::assertSame([], $payload->excludePatterns);
        self::assertNull($payload->extraRsyncOptions);
    }

    #[Test]
    public function carriesExcludePatternsAndExtraOptions(): void
    {
        $payload = new TransferPayload('/srv/app/fileadmin', '/var/www/fileadmin', ['*.log'], '--archive');

        self::assertSame(['*.log'], $payload->excludePatterns);
        self::assertSame('--archive', $payload->extraRsyncOptions);
    }

    #[Test]
    public function labelNamesWhatIsBeingTransferred(): void
    {
        self::assertSame('Transferring dump.gz', (new TransferPayload('/o/dump.gz', '/t/dump.gz'))->label());
    }

    #[Test]
    public function labelUsesTheDirectoryNameForDirectoryTransfers(): void
    {
        self::assertSame(
            'Transferring fileadmin',
            (new TransferPayload('/srv/app/fileadmin/', '/var/www/fileadmin'))->label(),
        );
    }
}
