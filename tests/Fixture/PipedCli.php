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

use Symfony\Component\Process\Process;

use function dirname;

/**
 * PipedCli.
 *
 * Runs bin/sync-tool as a real process with a piped, empty stdin, the way CI,
 * Deployer and `docker compose exec -T` start it. A CommandTester cannot show
 * this: it always hands the command a stream that counts as an answer.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class PipedCli
{
    /**
     * @param list<string>               $arguments
     * @param array<string, string>|null $env
     */
    public static function run(array $arguments, ?string $cwd = null, ?array $env = null): Process
    {
        $process = new Process([\PHP_BINARY, dirname(__DIR__, 2).'/bin/sync-tool', ...$arguments], $cwd, $env, '');
        $process->run();

        return $process;
    }
}
