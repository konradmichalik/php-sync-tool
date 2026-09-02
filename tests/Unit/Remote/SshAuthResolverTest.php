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
use KonradMichalik\SyncTool\Remote\SshAuthResolver;
use KonradMichalik\SyncTool\Tests\Fixture\{FakeRunnerFactory, FakeSshAgent, Plans, RecordingCommandRunner};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SshAuthResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SshAuthResolverTest extends TestCase
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
                'target' => ['host' => 't.example.com', 'user' => 'deploy'],
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
     * A loaded agent is a working way in, so there is nothing to ask about. It
     * used to be ignored unless the configuration named it.
     */
    #[Test]
    public function aLoadedAgentIsUsedWithoutBeingConfigured(): void
    {
        $config = $this->resolve(
            $this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy']]),
            Plans::receiver(),
            agentHasKeys: true,
        );

        self::assertSame([], $this->asked);
        self::assertTrue($config->sshAgent);
    }

    #[Test]
    public function anEmptyAgentStillLeadsToTheQuestion(): void
    {
        $config = $this->resolve(
            $this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy']]),
            Plans::receiver(),
            agentHasKeys: false,
        );

        self::assertSame(['deploy@o.example.com'], $this->asked);
        self::assertFalse($config->sshAgent);
    }

    #[Test]
    public function forcePasswordNeverConsultsTheAgent(): void
    {
        $agent = new FakeSshAgent(true);

        $this->resolverFor($agent)->resolve(
            $this->config([
                'force_password' => true,
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy'],
            ]),
            Plans::receiver(),
            static fn (string $endpoint): string => 'typed',
        );

        self::assertSame(0, $agent->probes);
    }

    #[Test]
    public function anAllLocalRunNeverConsultsTheAgent(): void
    {
        $agent = new FakeSshAgent(true);

        $this->resolverFor($agent)->resolve(
            $this->config(['origin' => ['path' => '/srv/a'], 'target' => ['path' => '/srv/b']]),
            Plans::syncLocal(),
            static fn (string $endpoint): string => 'typed',
        );

        self::assertSame(0, $agent->probes);
    }

    /**
     * rsync carries no password of its own, so without sshpass a password-based
     * transfer stops at a prompt: a hang in automation.
     */
    #[Test]
    public function sshpassIsUsedForAPasswordAuthenticatedTransferWhenInstalled(): void
    {
        $config = $this->resolve(
            $this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'password' => 'from-config']]),
            Plans::receiver(),
            sshpassInstalled: true,
        );

        self::assertTrue($config->useSshpass);
    }

    #[Test]
    public function sshpassStaysOffWhenTheBinaryIsMissing(): void
    {
        $config = $this->resolve(
            $this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'password' => 'from-config']]),
            Plans::receiver(),
            sshpassInstalled: false,
        );

        self::assertFalse($config->useSshpass);
    }

    /**
     * A key needs no password passing to rsync, so the binary is irrelevant.
     */
    #[Test]
    public function sshpassStaysOffForAKeyAuthenticatedTransfer(): void
    {
        $config = $this->resolve(
            $this->config(['origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'ssh_key' => '/home/deploy/.ssh/id_ed25519']]),
            Plans::receiver(),
            sshpassInstalled: true,
        );

        self::assertFalse($config->useSshpass);
    }

    #[Test]
    public function sshpassStaysOffWhenRsyncIsNotUsed(): void
    {
        $config = $this->resolve(
            $this->config([
                'use_rsync' => false,
                'origin' => ['host' => 'o.example.com', 'user' => 'deploy', 'password' => 'from-config'],
            ]),
            Plans::receiver(),
            sshpassInstalled: true,
        );

        self::assertFalse($config->useSshpass);
    }

    /**
     * The password that makes sshpass necessary may only exist after the prompt.
     */
    #[Test]
    public function sshpassIsAdoptedForAPasswordThatWasJustTypedIn(): void
    {
        $config = $this->resolve(
            $this->config(['force_password' => true, 'origin' => ['host' => 'o.example.com', 'user' => 'deploy']]),
            Plans::receiver(),
            sshpassInstalled: true,
        );

        self::assertSame(['deploy@o.example.com'], $this->asked);
        self::assertTrue($config->useSshpass);
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

    private function resolve(SyncConfig $config, SyncPlan $plan, bool $agentHasKeys = false, bool $sshpassInstalled = false): SyncConfig
    {
        return $this->resolverWith($agentHasKeys, $sshpassInstalled)->resolve($config, $plan, function (string $endpoint): string {
            $this->asked[] = $endpoint;

            return 'typed-for-'.$endpoint;
        });
    }

    private function resolverFor(FakeSshAgent $agent): SshAuthResolver
    {
        return new SshAuthResolver(
            $agent,
            runners: new FakeRunnerFactory(new RecordingCommandRunner(['sshpass -V' => ''])),
        );
    }

    /**
     * Both probes talk to the machine the suite runs on, so both are answered by
     * the fixture instead.
     */
    private function resolverWith(bool $agentHasKeys, bool $sshpassInstalled = false): SshAuthResolver
    {
        return new SshAuthResolver(
            new FakeSshAgent($agentHasKeys),
            runners: new FakeRunnerFactory(new RecordingCommandRunner(
                ['sshpass -V' => $sshpassInstalled ? 'sshpass 1.10' : ''],
            )),
        );
    }
}
