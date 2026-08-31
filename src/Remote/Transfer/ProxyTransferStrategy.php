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

namespace KonradMichalik\SyncTool\Remote\Transfer;

use Closure;
use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\LogChannel;
use KonradMichalik\SyncTool\Remote\{CommandRunner, RsyncCommandBuilder, RunnerFactory};
use KonradMichalik\SyncTool\Security\LogSanitizer;

use function basename;
use function bin2hex;
use function random_bytes;
use function rtrim;
use function sprintf;
use function sys_get_temp_dir;

/**
 * ProxyTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProxyTransferStrategy implements TransferStrategy
{
    /** @var Closure(string, LogChannel=): void */
    private Closure $log;

    public function __construct(
        private RunnerFactory $runners = new RunnerFactory(),
        private RsyncCommandBuilder $rsync = new RsyncCommandBuilder(),
        ?Closure $log = null,
    ) {
        $this->log = $log ?? static function (string $message, LogChannel $channel = LogChannel::Step): void {};
    }

    public function describe(): string
    {
        return ' via proxy (origin → local → target)';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $local = $this->runners->local();
        $staging = $this->createStagingDirectory($local);
        $localTemp = $staging.'/'.basename(rtrim($payload->targetPath, '/'));
        $options = $this->rsync->options($payload->extraRsyncOptions, $payload->excludePatterns, singleFile: $payload->singleFile);

        $pull = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->origin, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->origin, $config->useSshpass, $config->origin->jumpHost, $config->strictHostKeyChecking),
            $this->rsync->userHost($config->origin),
            $payload->originPath,
            '',
            $localTemp,
        );

        $push = $this->rsync->build(
            $this->rsync->passwordEnvironment($config->target, $config->useSshpass),
            $options,
            $this->rsync->authorization($config->target, $config->useSshpass, $config->target->jumpHost, $config->strictHostKeyChecking),
            '',
            $localTemp,
            $this->rsync->userHost($config->target),
            $payload->targetPath,
        );

        try {
            ($this->log)('  $ '.LogSanitizer::sanitize($pull), LogChannel::Command);
            $local->run($pull);
            ($this->log)('  $ '.LogSanitizer::sanitize($push), LogChannel::Command);
            $local->run($push);
        } finally {
            $local->run(sprintf('rm -rf %s', escapeshellarg($staging)), true);
        }
    }

    /**
     * A private directory to stage the payload in on its way between the two
     * remote hosts.
     *
     * The name used to be derived from the target path, so it was predictable from
     * the configuration alone and created with default permissions. On a shared
     * machine that let another user read the database dump passing through it, or
     * pre-create the path as a symlink pointing somewhere else.
     *
     * A random name closes the first, and `mkdir` closes the second by failing
     * outright when the path already exists. `-m 700` states the mode rather than
     * leaving it to the umask.
     */
    private function createStagingDirectory(CommandRunner $local): string
    {
        $path = sprintf('%s/php-sync-tool-%s', sys_get_temp_dir(), bin2hex(random_bytes(8)));

        // Not tolerated: a staging directory that is not there, or was already
        // there, means the transfer cannot be trusted to be private.
        $local->run(sprintf('mkdir -m 700 %s', escapeshellarg($path)));

        return $path;
    }
}
