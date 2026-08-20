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

namespace KonradMichalik\SyncTool\Tests\Unit\Mode;

use KonradMichalik\SyncTool\Enum\{SyncDirection, SyncOperation};
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Tests\Fixture\Plans;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * SyncPlanTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncPlanTest extends TestCase
{
    #[Test]
    #[DataProvider('plans')]
    public function keepsTheHistoricalModeNameAndItsDescription(SyncPlan $plan, string $label, string $description): void
    {
        self::assertSame($label, $plan->label());
        self::assertSame($description, $plan->description());
    }

    /**
     * @return iterable<string, array{SyncPlan, string, string}>
     */
    public static function plans(): iterable
    {
        yield 'receiver' => [Plans::receiver(), 'RECEIVER', '(REMOTE ➔ LOCAL)'];
        yield 'sender' => [Plans::sender(), 'SENDER', '(LOCAL ➔ REMOTE)'];
        yield 'proxy' => [Plans::proxy(), 'PROXY', '(REMOTE ➔ LOCAL ➔ REMOTE)'];
        yield 'remote copy' => [Plans::remoteCopy(), 'SYNC_REMOTE', '(REMOTE ➔ REMOTE)'];
        yield 'local sync' => [Plans::syncLocal(), 'SYNC_LOCAL', '(LOCAL ➔ LOCAL)'];
        yield 'local dump' => [Plans::dumpLocal(), 'DUMP_LOCAL', '(LOCAL, ONLY EXPORT)'];
        yield 'remote dump' => [Plans::dumpRemote(), 'DUMP_REMOTE', '(REMOTE, ONLY EXPORT)'];
        yield 'local import' => [Plans::importLocal(), 'IMPORT_LOCAL', '(LOCAL, ONLY IMPORT)'];
        yield 'remote import' => [Plans::importRemote(), 'IMPORT_REMOTE', '(REMOTE, ONLY IMPORT)'];
    }

    #[Test]
    public function readsTheEndpointLocalitiesFromTheDirection(): void
    {
        self::assertTrue(Plans::receiver()->isOriginRemote());
        self::assertFalse(Plans::receiver()->isTargetRemote());

        self::assertFalse(Plans::sender()->isOriginRemote());
        self::assertTrue(Plans::sender()->isTargetRemote());

        self::assertTrue(Plans::proxy()->isOriginRemote());
        self::assertTrue(Plans::proxy()->isTargetRemote());

        self::assertFalse(Plans::syncLocal()->isOriginRemote());
        self::assertFalse(Plans::syncLocal()->isTargetRemote());
    }

    #[Test]
    public function namesTheOperationItRuns(): void
    {
        self::assertTrue(Plans::dumpLocal()->isDump());
        self::assertFalse(Plans::dumpLocal()->isImport());

        self::assertTrue(Plans::importRemote()->isImport());
        self::assertFalse(Plans::importRemote()->isDump());

        self::assertFalse(Plans::receiver()->isDump());
        self::assertFalse(Plans::receiver()->isImport());
    }

    #[Test]
    public function distinguishesTwoRemoteHostsFromTwoEndpointsOnOneHost(): void
    {
        self::assertTrue(Plans::proxy()->isProxied());
        self::assertFalse(Plans::proxy()->isRemoteCopy());

        self::assertTrue(Plans::remoteCopy()->isRemoteCopy());
        self::assertFalse(Plans::remoteCopy()->isProxied());

        self::assertFalse(Plans::receiver()->isProxied());
        self::assertFalse(Plans::receiver()->isRemoteCopy());
    }

    #[Test]
    public function everythingButADumpWritesTheTargetAndIsTherefereProtectable(): void
    {
        self::assertTrue(Plans::receiver()->isProtectable());
        self::assertTrue(Plans::sender()->isProtectable());
        self::assertTrue(Plans::proxy()->isProtectable());
        self::assertTrue(Plans::syncLocal()->isProtectable());
        self::assertTrue(Plans::remoteCopy()->isProtectable());
        self::assertTrue(Plans::importLocal()->isProtectable());
        self::assertTrue(Plans::importRemote()->isProtectable());

        self::assertFalse(Plans::dumpLocal()->isProtectable());
        self::assertFalse(Plans::dumpRemote()->isProtectable());
    }

    #[Test]
    public function derivesTheDirectionFromTheTwoEndpointLocalities(): void
    {
        self::assertSame(SyncDirection::RemoteToLocal, SyncDirection::between(true, false));
        self::assertSame(SyncDirection::LocalToRemote, SyncDirection::between(false, true));
        self::assertSame(SyncDirection::RemoteToRemote, SyncDirection::between(true, true));
        self::assertSame(SyncDirection::LocalToLocal, SyncDirection::between(false, false));
    }

    #[Test]
    public function aPlanIsJustItsThreeAxes(): void
    {
        $plan = new SyncPlan(SyncDirection::RemoteToLocal, SyncOperation::Full);

        self::assertSame(SyncDirection::RemoteToLocal, $plan->direction);
        self::assertSame(SyncOperation::Full, $plan->operation);
        self::assertFalse($plan->sameHost);
    }
}
