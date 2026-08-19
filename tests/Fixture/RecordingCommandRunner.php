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
    /** @var list<string> */
    public array $commands = [];

    /** @var list<bool> */
    public array $allowFail = [];

    /**
     * @param array<string, string> $responses substring => canned stdout
     * @param array<string, string> $streams   substring => chunk handed to the output callback
     */
    public function __construct(
        private readonly array $responses = [],
        private readonly ?string $throwOn = null,
        private readonly array $streams = [],
    ) {}

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
