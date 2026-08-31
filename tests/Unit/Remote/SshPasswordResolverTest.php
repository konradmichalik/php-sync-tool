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

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Mode\SyncPlan;
use KonradMichalik\SyncTool\Remote\SshPasswordResolver;
use KonradMichalik\SyncTool\Tests\Fixture\Plans;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SshPasswordResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SshPasswordResolverTest extends TestCase
{
    /** @var list<string> */
    private array $asked = [];

    #[Test]
    public function asksForARemoteEndpointWithNoWayIn(): void
    {
        $config = $this->resolve($this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy']]), Plans::receiver());

        self::assertSame(['deploy@o.example.com'], $this->asked);
        self::assertSame('typed-for-deploy@o.example.com', $config->origin->password);
    }

    #[Test]
    public function leavesAConfiguredKeyAlone(): void
    {
        $this->resolve($this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'ssh_key' => '/home/deploy/.ssh/id_ed25519']]), Plans::receiver());

        self::assertSame([], $this->asked);
    }

    #[Test]
    public function leavesAConfiguredPasswordAlone(): void
    {
        $this->resolve($this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'password' => 'from-config']]), Plans::receiver());

        self::assertSame([], $this->asked);
    }

    #[Test]
    public function leavesAnAgentRunAlone(): void
    {
        $this->resolve($this->config(['ssh_agent' => true, 'origin' => ['host' => 'o.example.com', 'user' => 'deploy']]), Plans::receiver());

        self::assertSame([], $this->asked);
    }

    #[Test]
    public function neverAsksAboutALocalEndpoint(): void
    {
        $this->resolve($this->config(['origin' => ['path' => '/srv/a'], 'target' => ['path' => '/srv/b']]), Plans::syncLocal());

        self::assertSame([], $this->asked);
    }

    /**
     * The flag exists to override a key that cannot be used, so a configured key
     * is exactly the case it has to win against.
     */
    #[Test]
    public function forcePasswordAsksEvenWhenAKeyIsConfigured(): void
    {
        $config = $this->resolve(
            $this->config([
                'force_password' => true,
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'ssh_key' => '/home/deploy/.ssh/id_ed25519'],
            ]),
            Plans::receiver(),
        );

        self::assertSame(['deploy@o.example.com'], $this->asked);
        self::assertSame('typed-for-deploy@o.example.com', $config->origin->password);
    }

    /**
     * A dump-only run never opens a connection to the target, an import-only run
     * never opens one to the origin. Asking would be a prompt for a host the run
     * does not touch.
     */
    #[Test]
    public function skipsTheTargetOfADumpOnlyRun(): void
    {
        $this->resolve(
            $this->config([
                'force_password' => true,
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy'],
                'target' => ['host' => 'o.example.com', 'user' => 'deploy'],
            ]),
            Plans::dumpRemote(),
        );

        self::assertSame(['deploy@o.example.com'], $this->asked, 'the origin only');
    }

    #[Test]
    public function skipsTheOriginOfAnImportOnlyRun(): void
    {
        $this->resolve(
            $this->config([
                'force_password' => true,
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy'],
                'target' => ['host' => 't.example.com', 'user' => 'deploy'],
            ]),
            Plans::importRemote(),
        );

        self::assertSame(['deploy@t.example.com'], $this->asked, 'the target only');
    }

    #[Test]
    public function asksForBothEndpointsOfAProxyRun(): void
    {
        $this->resolve(
            $this->config([
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy'],
                'target' => ['host' => 't.example.com', 'user' => 'deploy'],
            ]),
            Plans::proxy(),
        );

        self::assertSame(['deploy@o.example.com', 'deploy@t.example.com'], $this->asked);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function config(array $overrides): SyncConfig
    {
        return SyncConfig::fromArray($overrides + [
            'origin' => ['path' => '/srv/a'],
            'target' => ['path' => '/srv/b'],
        ]);
    }

    private function resolve(SyncConfig $config, SyncPlan $plan): SyncConfig
    {
        return (new SshPasswordResolver())->resolve($config, $plan, function (string $endpoint): string {
            $this->asked[] = $endpoint;

            return 'typed-for-'.$endpoint;
        });
    }
}
