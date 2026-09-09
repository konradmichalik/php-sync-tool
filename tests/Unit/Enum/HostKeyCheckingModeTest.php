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

use KonradMichalik\SyncTool\Enum\HostKeyCheckingMode;
use KonradMichalik\SyncTool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HostKeyCheckingModeTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class HostKeyCheckingModeTest extends TestCase
{
    #[Test]
    public function defaultsToStrictForNull(): void
    {
        self::assertSame(HostKeyCheckingMode::Strict, HostKeyCheckingMode::fromConfigValue(null));
    }

    #[Test]
    public function trueIsStrictAndFalseIsOff(): void
    {
        self::assertSame(HostKeyCheckingMode::Strict, HostKeyCheckingMode::fromConfigValue(true));
        self::assertSame(HostKeyCheckingMode::Off, HostKeyCheckingMode::fromConfigValue(false));
    }

    #[Test]
    public function acceptNewStringIsParsedCaseInsensitively(): void
    {
        self::assertSame(HostKeyCheckingMode::AcceptNew, HostKeyCheckingMode::fromConfigValue('accept-new'));
        self::assertSame(HostKeyCheckingMode::AcceptNew, HostKeyCheckingMode::fromConfigValue('Accept-New'));
    }

    #[Test]
    public function unknownStringThrowsAndListsValidValues(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Use true, false or accept-new.');
        HostKeyCheckingMode::fromConfigValue('yes');
    }

    #[Test]
    public function unsupportedTypeThrows(): void
    {
        $this->expectException(ConfigException::class);
        HostKeyCheckingMode::fromConfigValue(123);
    }
}
