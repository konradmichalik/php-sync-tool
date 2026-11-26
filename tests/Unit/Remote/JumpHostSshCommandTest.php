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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote;

use KonradMichalik\SyncTool\Config\{ClientConfig, JumpHostConfig};
use KonradMichalik\SyncTool\Enum\HostKeyCheckingMode;
use KonradMichalik\SyncTool\Remote\JumpHostSshCommand;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * JumpHostSshCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class JumpHostSshCommandTest extends TestCase
{
    #[Test]
    public function buildsProxyJumpInvocationWithKeyAndPort(): void
    {
        $cmd = (new JumpHostSshCommand())->build(
            new ClientConfig(host: 'db.internal', user: 'app', sshKey: '/keys/id_rsa', port: 2222),
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump'),
            'mysqldump mydb',
            HostKeyCheckingMode::Strict,
        );

        self::assertStringStartsWith('ssh ', $cmd);
        self::assertStringContainsString("-J 'jump@bastion.example.com'", $cmd);
        self::assertStringContainsString("-i '/keys/id_rsa'", $cmd);
        self::assertStringContainsString('-p 2222', $cmd);
        self::assertStringContainsString("'app@db.internal'", $cmd);
        self::assertStringContainsString("'mysqldump mydb'", $cmd);
    }

    #[Test]
    public function jumpPortAppendedWhenNonDefault(): void
    {
        $cmd = (new JumpHostSshCommand())->build(
            new ClientConfig(host: 'db.internal', user: 'app'),
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump', port: 2200),
            'echo ok',
            HostKeyCheckingMode::Strict,
        );

        self::assertStringContainsString("-J 'jump@bastion.example.com:2200'", $cmd);
    }

    #[Test]
    public function detectsAStalledConnection(): void
    {
        $cmd = (new JumpHostSshCommand())->build(
            new ClientConfig(host: 'db.internal', user: 'app'),
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump'),
            'echo ok',
            HostKeyCheckingMode::Strict,
        );

        self::assertStringContainsString('-o ServerAliveInterval=30 -o ServerAliveCountMax=4', $cmd);
    }

    /**
     * @return iterable<string, array{HostKeyCheckingMode, string}>
     */
    public static function hostKeyCheckingModes(): iterable
    {
        yield 'strict' => [HostKeyCheckingMode::Strict, 'yes'];
        yield 'accept-new' => [HostKeyCheckingMode::AcceptNew, 'accept-new'];
        yield 'off' => [HostKeyCheckingMode::Off, 'no'];
    }

    #[Test]
    #[DataProvider('hostKeyCheckingModes')]
    public function honoursTheHostKeyCheckingMode(HostKeyCheckingMode $mode, string $expected): void
    {
        $cmd = (new JumpHostSshCommand())->build(
            new ClientConfig(host: 'db.internal', user: 'app'),
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump'),
            'echo ok',
            $mode,
        );

        self::assertStringContainsString('-o StrictHostKeyChecking='.$expected.' ', $cmd);
    }

    #[Test]
    public function omitsKeyWhenUnset(): void
    {
        $cmd = (new JumpHostSshCommand())->build(
            new ClientConfig(host: 'db.internal', user: 'app'),
            new JumpHostConfig(host: 'bastion.example.com', user: 'jump'),
            'echo ok',
            HostKeyCheckingMode::Strict,
        );

        self::assertStringNotContainsString('-i ', $cmd);
    }
}
