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

namespace KonradMichalik\SyncTool\Tests\Unit\Lifecycle;

use Closure;
use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\LifecyclePhase;
use KonradMichalik\SyncTool\Lifecycle\ScriptRunner;
use KonradMichalik\SyncTool\Remote\CommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ScriptRunnerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ScriptRunnerTest extends TestCase
{
    #[Test]
    public function scriptsForCollectsGlobalThenOriginThenTarget(): void
    {
        $config = new SyncConfig(
            scripts: ['before' => 'global-before'],
            origin: new ClientConfig(scripts: ['before' => 'origin-before']),
            target: new ClientConfig(scripts: ['before' => 'target-before', 'after' => 'target-after']),
        );

        $runner = new ScriptRunner();

        self::assertSame(
            ['global-before', 'origin-before', 'target-before'],
            $runner->scriptsFor($config, LifecyclePhase::Before),
        );
        self::assertSame(['target-after'], $runner->scriptsFor($config, LifecyclePhase::After));
        self::assertSame([], $runner->scriptsFor($config, LifecyclePhase::Error));
    }

    /**
     * Every example in the documentation writes `script`, while the code read
     * `scripts`, so documented lifecycle commands never ran. Both spellings are
     * accepted now.
     */
    #[Test]
    public function theDocumentedSingularSpellingIsPickedUp(): void
    {
        $config = SyncConfig::fromArray([
            'script' => ['before' => 'global-before'],
            'origin' => ['script' => ['before' => 'origin-before']],
            'target' => ['scripts' => ['before' => 'target-before']],
        ]);

        self::assertSame(
            ['global-before', 'origin-before', 'target-before'],
            (new ScriptRunner())->scriptsFor($config, LifecyclePhase::Before),
        );
    }

    /**
     * A configuration carrying both keys is a mistake worth resolving predictably:
     * the plural one wins, because that is what ran before.
     */
    #[Test]
    public function thePluralSpellingWinsWhenBothArePresent(): void
    {
        $config = SyncConfig::fromArray(['scripts' => ['before' => 'plural'], 'script' => ['before' => 'singular']]);

        self::assertSame(['plural'], (new ScriptRunner())->scriptsFor($config, LifecyclePhase::Before));
    }

    #[Test]
    public function runExecutesEachResolvedScript(): void
    {
        $config = new SyncConfig(
            scripts: ['after' => 'echo one'],
            target: new ClientConfig(scripts: ['after' => 'echo two']),
        );

        $recorder = new class implements CommandRunner {
            /** @var list<string> */
            public array $commands = [];

            public function run(string $command, bool $allowFail = false, ?Closure $onOutput = null): string
            {
                $this->commands[] = $command;

                return '';
            }
        };

        (new ScriptRunner())->run($recorder, $config, LifecyclePhase::After);

        self::assertSame(['echo one', 'echo two'], $recorder->commands);
    }
}
