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

namespace KonradMichalik\SyncTool\Tests\Unit\Enum;

use KonradMichalik\SyncTool\Enum\OutputMode;
use KonradMichalik\SyncTool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OutputModeTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class OutputModeTest extends TestCase
{
    #[Test]
    public function defaultsToInteractiveForNullOrEmpty(): void
    {
        self::assertSame(OutputMode::Interactive, OutputMode::fromString(null));
        self::assertSame(OutputMode::Interactive, OutputMode::fromString(''));
    }

    #[Test]
    public function parsesKnownModesCaseInsensitively(): void
    {
        self::assertSame(OutputMode::Ci, OutputMode::fromString('CI'));
        self::assertSame(OutputMode::Json, OutputMode::fromString('json'));
        self::assertSame(OutputMode::Quiet, OutputMode::fromString('quiet'));
    }

    #[Test]
    public function unknownModeThrows(): void
    {
        $this->expectException(ConfigException::class);
        OutputMode::fromString('fancy');
    }
}
