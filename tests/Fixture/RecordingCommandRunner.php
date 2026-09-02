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

namespace KonradMichalik\SyncTool\Tests\Fixture;

use Closure;
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\CommandRunner;

use function str_contains;

/**
 * RecordingCommandRunner.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class RecordingCommandRunner implements CommandRunner
{
    /**
     * The machine a test runs on is assumed to have rsync, the way nearly every
     * machine does. A test about its absence overrides the key with an empty
     * string; without a default here, every unrelated test would silently take
     * the no-rsync path.
     */
    private const AMBIENT_RESPONSES = ['rsync --version' => 'rsync  version 3.2.7  protocol version 31'];
    /** @var list<string> */
    public array $commands = [];

    /** @var list<bool> */
    public array $allowFail = [];

    /** @var array<string, string> */
    private readonly array $responses;

    /**
     * @param array<string, string> $responses substring => canned stdout
     * @param array<string, string> $streams   substring => chunk handed to the output callback
     */
    public function __construct(
        array $responses = [],
        private readonly ?string $throwOn = null,
        private readonly array $streams = [],
    ) {
        $this->responses = $responses + self::AMBIENT_RESPONSES;
    }

    public function run(string $command, bool $allowFail = false, ?Closure $onOutput = null): string
    {
        $this->commands[] = $command;
        $this->allowFail[] = $allowFail;

        if (null !== $onOutput) {
            foreach ($this->streams as $needle => $chunk) {
                if (str_contains($command, $needle)) {
                    $onOutput($chunk);
                }
            }
        }

        if (null !== $this->throwOn && str_contains($command, $this->throwOn)) {
            throw new SyncException('command failed: '.$command);
        }

        foreach ($this->responses as $needle => $output) {
            if (str_contains($command, $needle)) {
                return $output;
            }
        }

        return '';
    }

    public function ran(string $needle): bool
    {
        foreach ($this->commands as $command) {
            if (str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }
}
