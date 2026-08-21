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

use KonradMichalik\SyncTool\Database\RemoteFileWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RemoteFileWriterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RemoteFileWriterTest extends TestCase
{
    #[Test]
    public function remoteWriteCommandBase64Decodes(): void
    {
        $command = (new RemoteFileWriter())->remoteWriteCommand("[client]\nuser=x\n", '/tmp/.my_x.cnf');

        self::assertSame(
            "(umask 077 && echo '".base64_encode("[client]\nuser=x\n")."' | base64 -d > /tmp/.my_x.cnf) && chmod 600 /tmp/.my_x.cnf && echo 'OK'",
            $command,
        );
    }

    /**
     * The file holds a database password, so it must never exist with the shell's
     * default mode, not even for the duration of the redirection.
     */
    #[Test]
    public function remoteWriteCommandCreatesTheFileWithARestrictiveUmask(): void
    {
        self::assertStringStartsWith(
            '(umask 077 && ',
            (new RemoteFileWriter())->remoteWriteCommand('x', '/tmp/.my_x.cnf'),
        );
    }

    #[Test]
    public function remoteWriteCommandQuotesAnAwkwardPath(): void
    {
        $command = (new RemoteFileWriter())->remoteWriteCommand('x', '/tmp/a b;id.cnf');

        self::assertStringContainsString("> '/tmp/a b;id.cnf')", $command);
        self::assertStringContainsString("chmod 600 '/tmp/a b;id.cnf'", $command);
    }
}
