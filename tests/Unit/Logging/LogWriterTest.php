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

namespace MoveElevator\DbSyncTool\Tests\Unit\Logging;

use MoveElevator\DbSyncTool\Logging\LogWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * LogWriterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class LogWriterTest extends TestCase
{
    #[Test]
    public function plainPassesMessageThroughAndAppendsToFile(): void
    {
        $captured = [];
        $file = sys_get_temp_dir().'/logwriter_'.uniqid();

        $writer = new LogWriter(false, $file, static function (string $line) use (&$captured): void {
            $captured[] = $line;
        });
        $writer->log('hello');
        $writer->log('world');

        self::assertSame(['hello', 'world'], $captured);
        self::assertSame("hello\nworld\n", file_get_contents($file));

        unlink($file);
    }

    #[Test]
    public function jsonFormatsWithInjectedClock(): void
    {
        $captured = [];

        $writer = new LogWriter(
            true,
            null,
            static function (string $line) use (&$captured): void { $captured[] = $line; },
            static fn (): string => '2026-01-01T00:00:00+00:00',
        );
        $writer->log('dump /var/www/x');

        self::assertSame(
            ['{"time":"2026-01-01T00:00:00+00:00","message":"dump /var/www/x"}'],
            $captured,
        );
    }

    #[Test]
    public function nullFileDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        (new LogWriter(false, null, static function (string $line): void {}))->log('x');
    }
}
