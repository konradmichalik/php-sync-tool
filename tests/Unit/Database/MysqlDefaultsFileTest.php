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

use KonradMichalik\SyncTool\Config\DatabaseConfig;
use KonradMichalik\SyncTool\Database\MysqlDefaultsFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MysqlDefaultsFileTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class MysqlDefaultsFileTest extends TestCase
{
    #[Test]
    public function buildContentQuotesAndEscapesPassword(): void
    {
        $content = (new MysqlDefaultsFile())->buildContent(
            new DatabaseConfig(name: 'db', host: 'localhost', user: 'admin', password: 'pa"ss\\word', port: 3307),
        );

        self::assertSame(
            "[client]\nuser=admin\npassword=\"pa\\\"ss\\\\word\"\nhost=localhost\nport=3307\n",
            $content,
        );
    }

    #[Test]
    public function buildContentOmitsEmptyHostAndZeroPort(): void
    {
        $content = (new MysqlDefaultsFile())->buildContent(
            new DatabaseConfig(user: 'admin', password: 'secret'),
        );

        self::assertSame("[client]\nuser=admin\npassword=\"secret\"\n", $content);
    }

    #[Test]
    public function generatePathMatchesTmpPattern(): void
    {
        $path = (new MysqlDefaultsFile())->generatePath();

        self::assertMatchesRegularExpression('#^/tmp/\.my_[0-9a-f]{16}\.cnf$#', $path);
    }

    #[Test]
    public function buildContentAppendsSslFalseWhenDisabled(): void
    {
        $content = (new MysqlDefaultsFile())->buildContent(
            new DatabaseConfig(host: 'db', user: 'admin', password: 'secret', port: 3306, sslDisabled: true),
        );

        self::assertSame("[client]\nuser=admin\npassword=\"secret\"\nhost=db\nport=3306\nssl=false\n", $content);
    }

    #[Test]
    public function buildContentOmitsSslWhenEnabled(): void
    {
        $content = (new MysqlDefaultsFile())->buildContent(
            new DatabaseConfig(user: 'admin', password: 'secret'),
        );

        self::assertStringNotContainsString('ssl', $content);
    }
}
