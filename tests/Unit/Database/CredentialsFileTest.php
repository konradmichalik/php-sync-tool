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

namespace KonradMichalik\SyncTool\Tests\Unit\Database;

use KonradMichalik\SyncTool\Database\CredentialsFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CredentialsFileTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class CredentialsFileTest extends TestCase
{
    #[Test]
    public function remoteWriteCommandBase64Decodes(): void
    {
        $command = (new CredentialsFile())->remoteWriteCommand("[client]\nuser=x\n", '/tmp/.my_x.cnf');

        self::assertSame(
            "echo '".base64_encode("[client]\nuser=x\n")."' | base64 -d > /tmp/.my_x.cnf && chmod 600 /tmp/.my_x.cnf && echo 'OK'",
            $command,
        );
    }
}
