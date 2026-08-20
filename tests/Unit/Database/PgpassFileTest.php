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
use KonradMichalik\SyncTool\Database\PgpassFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PgpassFileTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class PgpassFileTest extends TestCase
{
    #[Test]
    public function buildsOneLineInPgpassFormat(): void
    {
        $content = (new PgpassFile())->buildContent(
            new DatabaseConfig(name: 'app', host: 'db.example.com', user: 'u', password: 'p', port: 5432),
        );

        self::assertSame("db.example.com:5432:app:u:p\n", $content);
    }

    #[Test]
    public function fallsBackToLocalhostAndTheDefaultPort(): void
    {
        $content = (new PgpassFile())->buildContent(new DatabaseConfig(name: 'app', user: 'u', password: 'p'));

        self::assertSame("localhost:5432:app:u:p\n", $content);
    }

    #[Test]
    public function escapesColonsAndBackslashesInEveryField(): void
    {
        $content = (new PgpassFile())->buildContent(
            new DatabaseConfig(name: 'app', host: 'db', user: 'u', password: 'pa:ss\\word', port: 5432),
        );

        self::assertSame("db:5432:app:u:pa\\:ss\\\\word\n", $content);
    }

    #[Test]
    public function generatesARandomPathUnderTmp(): void
    {
        self::assertMatchesRegularExpression('#^/tmp/\.pgpass_[0-9a-f]{16}$#', (new PgpassFile())->generatePath());
    }
}
