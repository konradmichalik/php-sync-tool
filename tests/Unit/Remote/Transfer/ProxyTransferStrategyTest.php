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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote\Transfer;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Remote\Transfer\{ProxyTransferStrategy, TransferPayload};
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ProxyTransferStrategyTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ProxyTransferStrategyTest extends TestCase
{
    #[Test]
    public function pullsFromOriginThenPushesToTargetViaLocalTemp(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/o/dump.gz', '/t/dump.gz', singleFile: true),
        );

        self::assertTrue($recorder->ran('deploy@o.example.com:/o/dump.gz'), 'pulls from origin');
        self::assertTrue($recorder->ran('deploy@t.example.com:/t/dump.gz'), 'pushes to target');
        self::assertMatchesRegularExpression(
            "#mkdir -m 700 '[^']*/php-sync-tool-[0-9a-f]{16}'#",
            implode("\n", $recorder->commands),
            'stages in a private directory whose name cannot be guessed from the configuration',
        );
        self::assertMatchesRegularExpression(
            '#/php-sync-tool-[0-9a-f]{16}/dump\.gz#',
            implode("\n", $recorder->commands),
            'the payload keeps its name inside that directory',
        );
        self::assertTrue($recorder->ran('rm -rf'), 'cleans up the local temp path');
    }

    #[Test]
    public function worksForDirectoryEntriesToo(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin', '/srv/web/fileadmin', ['*.log']),
        );

        self::assertMatchesRegularExpression(
            '#/php-sync-tool-[0-9a-f]{16}/fileadmin#',
            implode("\n", $recorder->commands),
        );
        self::assertTrue($recorder->ran("--exclude='*.log'"));
    }

    #[Test]
    public function pushesTheContentsOfADirectoryNotTheDirectoryItself(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin/', '/srv/web/fileadmin/'),
        );

        $push = array_values(array_filter(
            $recorder->commands,
            static fn (string $command): bool => str_contains($command, 'deploy@t.example.com:'),
        ));

        self::assertCount(1, $push);
        self::assertMatchesRegularExpression(
            "#/php-sync-tool-[0-9a-f]{16}/'? '?deploy@t\\.example\\.com:#",
            $push[0],
            'pushing the staged directory itself makes rsync create fileadmin/fileadmin on the target',
        );
    }

    #[Test]
    public function pushesAnOriginWithoutTrailingSlashAsTheNamedEntryOnly(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/srv/app/fileadmin', '/srv/web/'),
        );

        $push = array_values(array_filter(
            $recorder->commands,
            static fn (string $command): bool => str_contains($command, 'deploy@t.example.com:'),
        ));

        self::assertCount(1, $push);
        self::assertMatchesRegularExpression(
            "#/php-sync-tool-[0-9a-f]{16}/fileadmin'? '?deploy@t\\.example\\.com:#",
            $push[0],
            'pushing the contents of a staging dir named after the target parent would --delete its siblings',
        );
    }

    #[Test]
    public function pushesASingleFileAsIs(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder)))->transfer(
            $config,
            new TransferPayload('/o/dump.gz', '/t/dump.gz', singleFile: true),
        );

        $push = array_values(array_filter(
            $recorder->commands,
            static fn (string $command): bool => str_contains($command, 'deploy@t.example.com:'),
        ));

        self::assertCount(1, $push);
        self::assertMatchesRegularExpression(
            "#/php-sync-tool-[0-9a-f]{16}/dump\\.gz'? '?deploy@t\\.example\\.com:#",
            $push[0],
            'a file has no contents to push, a trailing slash would make rsync fail',
        );
    }

    #[Test]
    public function describeMentionsProxy(): void
    {
        self::assertSame(' via proxy (origin → local → target)', (new ProxyTransferStrategy())->describe());
    }

    #[Test]
    public function logsBothPullAndPushCommandsBeforeRunningThem(): void
    {
        $config = self::config();
        $recorder = new RecordingCommandRunner();
        $logs = [];

        (new ProxyTransferStrategy(new FakeRunnerFactory($recorder), log: static function (string $message) use (&$logs): void {
            $logs[] = $message;
        }))->transfer($config, new TransferPayload('/o/dump.gz', '/t/dump.gz', singleFile: true));

        self::assertCount(2, $logs, 'logs once per leg (pull, push)');
        self::assertStringContainsString('deploy@o.example.com:/o/dump.gz', $logs[0]);
        self::assertStringContainsString('deploy@t.example.com:/t/dump.gz', $logs[1]);
    }

    private static function config(): SyncConfig
    {
        return SyncConfig::fromArray([
            'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'db' => ['name' => 'a', 'user' => 'a', 'password' => 'a']],
            'target' => ['host' => 't.example.com', 'user' => 'deploy', 'db' => ['name' => 'b', 'user' => 'b', 'password' => 'b']],
        ]);
    }
}
