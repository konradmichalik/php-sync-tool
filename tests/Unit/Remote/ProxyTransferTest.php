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

namespace MoveElevator\DbSyncTool\Tests\Unit\Remote;

use MoveElevator\DbSyncTool\Config\SyncConfig;
use MoveElevator\DbSyncTool\Remote\ProxyTransfer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * ProxyTransferTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class ProxyTransferTest extends TestCase
{
    #[Test]
    public function pullCommandFetchesFromOriginToLocalTemp(): void
    {
        [$pull] = (new ProxyTransfer())->commands($this->config(), '/o/dump.gz', '/tmp/dump.gz', '/t/dump.gz');

        self::assertStringContainsString('deploy@o.example.com:/o/dump.gz', $pull);
        self::assertStringContainsString('/tmp/dump.gz', $pull);
        self::assertStringNotContainsString('t.example.com', $pull);
    }

    #[Test]
    public function pushCommandSendsLocalTempToTarget(): void
    {
        [, $push] = (new ProxyTransfer())->commands($this->config(), '/o/dump.gz', '/tmp/dump.gz', '/t/dump.gz');

        self::assertStringContainsString('deploy@t.example.com:/t/dump.gz', $push);
        self::assertStringContainsString('/tmp/dump.gz', $push);
        self::assertStringNotContainsString('o.example.com', $push);
    }

    private function config(): SyncConfig
    {
        return SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
    }
}
