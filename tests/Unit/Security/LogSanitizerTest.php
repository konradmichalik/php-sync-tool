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

namespace MoveElevator\DbSyncTool\Tests\Unit\Security;

use MoveElevator\DbSyncTool\Security\LogSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LogSanitizerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LogSanitizerTest extends TestCase
{
    #[Test]
    public function mysqlPasswordSingleQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize("mysql -uuser -p'secretpassword' -h localhost");

        self::assertStringNotContainsString('secretpassword', $result);
        self::assertStringContainsString("-p'***'", $result);
    }

    #[Test]
    public function mysqlPasswordDoubleQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize('mysql -uuser -p"secretpassword" -h localhost');

        self::assertStringNotContainsString('secretpassword', $result);
        self::assertStringContainsString('-p"***"', $result);
    }

    #[Test]
    public function mysqlPasswordNoQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize('mysql -uuser -psecretpassword -h localhost');

        self::assertStringNotContainsString('secretpassword', $result);
        self::assertStringContainsString('-p***', $result);
    }

    #[Test]
    public function sshpassSingleQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize("SSHPASS='sshsecret' sshpass -e ssh user@host");

        self::assertStringNotContainsString('sshsecret', $result);
        self::assertStringContainsString('SSHPASS=', $result);
        self::assertStringContainsString('***', $result);
    }

    #[Test]
    public function sshpassDoubleQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize('SSHPASS="sshsecret" sshpass -e ssh user@host');

        self::assertStringNotContainsString('sshsecret', $result);
        self::assertStringContainsString('SSHPASS=', $result);
        self::assertStringContainsString('***', $result);
    }

    #[Test]
    public function sshpassNoQuoteIsMasked(): void
    {
        $result = LogSanitizer::sanitize('SSHPASS=sshsecret sshpass -e ssh user@host');

        self::assertStringNotContainsString('sshsecret', $result);
        self::assertStringContainsString('SSHPASS=***', $result);
    }

    #[Test]
    public function defaultsFilePathIsMasked(): void
    {
        $result = LogSanitizer::sanitize("mysql --defaults-file=/tmp/.my_abc123.cnf -e 'SELECT 1'");

        self::assertStringNotContainsString('/tmp/.my_abc123.cnf', $result);
        self::assertStringContainsString('--defaults-file=***', $result);
    }

    #[Test]
    public function base64EncodedCredentialsAreMasked(): void
    {
        $result = LogSanitizer::sanitize("echo 'dXNlcj10ZXN0CnBhc3N3b3JkPXNlY3JldA==' | base64");

        self::assertStringNotContainsString('dXNlcj10ZXN0CnBhc3N3b3JkPXNlY3JldA==', $result);
        self::assertStringContainsString("echo '***' | base64", $result);
    }

    #[Test]
    public function multipleCredentialsAreAllMasked(): void
    {
        $result = LogSanitizer::sanitize("mysql -uuser -p'pass1' && mysql -uadmin -p'pass2'");

        self::assertStringNotContainsString('pass1', $result);
        self::assertStringNotContainsString('pass2', $result);
        self::assertSame(2, substr_count($result, "-p'***'"));
    }

    #[Test]
    public function commandsWithoutCredentialsAreUnchanged(): void
    {
        $command = 'ls -la /var/www';

        self::assertSame($command, LogSanitizer::sanitize($command));
    }

    #[Test]
    public function emptyPasswordIsStillMasked(): void
    {
        $result = LogSanitizer::sanitize("mysql -uuser -p'' -h localhost");

        self::assertStringContainsString("-p'***'", $result);
    }

    #[Test]
    public function specialCharsInPasswordAreMasked(): void
    {
        $result = LogSanitizer::sanitize("mysql -uuser -p'p@ss\$word!#%' -h localhost");

        self::assertStringNotContainsString('p@ss$word!#%', $result);
        self::assertStringContainsString("-p'***'", $result);
    }

    #[Test]
    public function nonSensitivePartsArePreserved(): void
    {
        $result = LogSanitizer::sanitize('mysqldump --defaults-file=/tmp/.my.cnf --single-transaction dbname');

        self::assertStringContainsString('--single-transaction', $result);
        self::assertStringContainsString('dbname', $result);
        self::assertStringContainsString('--defaults-file=***', $result);
    }
}
