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

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Mode\SyncSteps;
use KonradMichalik\SyncTool\Tests\Fixture\Plans;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SyncStepsTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncStepsTest extends TestCase
{
    #[Test]
    public function aPlainSyncDumpsTransfersAndImports(): void
    {
        self::assertSame(3, (new SyncSteps())->count($this->config(), Plans::receiver()));
    }

    #[Test]
    public function dumpOnlyModesSkipTransferAndImport(): void
    {
        self::assertSame(1, (new SyncSteps())->count($this->config(), Plans::dumpLocal()));
    }

    #[Test]
    public function importOnlyModesSkipTheDump(): void
    {
        self::assertSame(2, (new SyncSteps())->count($this->config(), Plans::importLocal()));
    }

    #[Test]
    public function keepingTheDumpSkipsTheImport(): void
    {
        self::assertSame(2, (new SyncSteps())->count($this->config(['keep_dump' => true]), Plans::receiver()));
    }

    /**
     * The whole post_sql block runs in one batched invocation, so it is a single
     * step no matter how many statements it holds.
     */
    #[Test]
    public function postSqlIsOneStepAndTheAfterDumpAnother(): void
    {
        $config = $this->config([
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
                'after_dump' => '/tmp/extra.sql.gz',
                'post_sql' => ['UPDATE a SET b = 1', '', 'UPDATE c SET d = 2'],
            ],
        ]);

        self::assertSame(5, (new SyncSteps())->count($config, Plans::receiver()));
    }

    #[Test]
    public function anEmptyPostSqlBlockCountsForNothing(): void
    {
        $config = $this->config([
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
                'post_sql' => ['', ''],
            ],
        ]);

        self::assertSame(3, (new SyncSteps())->count($config, Plans::receiver()));
    }

    #[Test]
    public function everyFileEntryCountsAsAStep(): void
    {
        $config = $this->config([
            'with_files' => true,
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin'], ['origin' => 'uploads', 'target' => 'uploads']],
        ]);

        self::assertSame(5, (new SyncSteps())->count($config, Plans::receiver()));
    }

    #[Test]
    public function filesOnlyCountsNothingButTheFiles(): void
    {
        $config = $this->config([
            'files_only' => true,
            'files' => [['origin' => 'fileadmin', 'target' => 'fileadmin']],
        ]);

        self::assertSame(1, (new SyncSteps())->count($config, Plans::receiver()));
    }

    #[Test]
    public function neverReportsZeroStepsSoTheDisplayStaysSane(): void
    {
        self::assertSame(1, (new SyncSteps())->count($this->config(['files_only' => true]), Plans::receiver()));
    }

    #[Test]
    public function maskingCountsAsOneStepRegardlessOfRuleCount(): void
    {
        $config = $this->config([
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p'],
                'anonymize' => ['fe_users' => ['email' => 'email', 'password' => 'hash']],
            ],
        ]);

        self::assertSame(4, (new SyncSteps())->count($config, Plans::receiver()));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function config(array $overrides = []): SyncConfig
    {
        return SyncConfig::fromArray($overrides + [
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app', 'user' => 'u', 'password' => 'p']],
        ]);
    }
}
