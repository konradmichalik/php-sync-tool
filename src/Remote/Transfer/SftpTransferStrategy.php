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

use FilesystemIterator;
use KonradMichalik\SyncTool\Config\{ClientConfig, SyncConfig};
use KonradMichalik\SyncTool\Exception\SyncException;
use KonradMichalik\SyncTool\Remote\SshClientFactory;
use phpseclib3\Net\SFTP;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function basename;
use function dirname;
use function in_array;
use function is_dir;
use function mkdir;
use function rtrim;
use function sprintf;
use function strlen;
use function substr;

/**
 * SftpTransferStrategy.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class SftpTransferStrategy implements TransferStrategy
{
    public function __construct(
        private SshClientFactory $factory = new SshClientFactory(),
    ) {}

    public function describe(): string
    {
        return ' via SFTP';
    }

    public function transfer(SyncConfig $config, TransferPayload $payload): void
    {
        $originRemote = $config->origin->isRemote();
        $targetRemote = $config->target->isRemote();

        if ($originRemote && $targetRemote) {
            throw new SyncException('SFTP fallback does not support remote-to-remote (proxy) transfers; enable rsync for this sync mode.');
        }

        if ($originRemote) {
            $this->download($config, $payload);

            return;
        }

        $this->upload($config, $payload);
    }

    private function download(SyncConfig $config, TransferPayload $payload): void
    {
        $sftp = $this->connect($config, $config->origin);

        if ($sftp->is_dir($payload->originPath)) {
            $this->downloadDirectory($sftp, $payload->originPath, $payload->targetPath, $payload->excludePatterns);

            return;
        }

        if (false === $sftp->get($payload->originPath, $payload->targetPath)) {
            throw new SyncException(sprintf('SFTP download failed: %s', $payload->originPath));
        }
    }

    private function upload(SyncConfig $config, TransferPayload $payload): void
    {
        $sftp = $this->connect($config, $config->target);

        if (is_dir($payload->originPath)) {
            $this->uploadDirectory($sftp, $payload->originPath, $payload->targetPath, $payload->excludePatterns);

            return;
        }

        if (false === $sftp->put($payload->targetPath, $payload->originPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new SyncException(sprintf('SFTP upload failed: %s', $payload->targetPath));
        }
    }

    /**
     * @param list<string> $excludePatterns
     */
    private function downloadDirectory(SFTP $sftp, string $originDir, string $targetDir, array $excludePatterns): void
    {
        $entries = $sftp->nlist($originDir, true);
        if (false === $entries) {
            throw new SyncException(sprintf('SFTP directory listing failed: %s', $originDir));
        }

        foreach ($entries as $relativePath) {
            if (in_array(basename($relativePath), ['.', '..'], true) || ExcludeMatcher::isPathExcluded($relativePath, $excludePatterns)) {
                continue;
            }

            $remotePath = rtrim($originDir, '/').'/'.$relativePath;
            $localPath = rtrim($targetDir, '/').'/'.$relativePath;

            if ($sftp->is_dir($remotePath)) {
                $this->ensureLocalDirectory($localPath);

                continue;
            }

            $this->ensureLocalDirectory(dirname($localPath));

            if (false === $sftp->get($remotePath, $localPath)) {
                throw new SyncException(sprintf('SFTP download failed: %s', $remotePath));
            }
        }
    }

    /**
     * @param list<string> $excludePatterns
     */
    private function uploadDirectory(SFTP $sftp, string $originDir, string $targetDir, array $excludePatterns): void
    {
        $originDir = rtrim($originDir, '/');
        $targetDir = rtrim($targetDir, '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($originDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($originDir) + 1);
            if (ExcludeMatcher::isPathExcluded($relativePath, $excludePatterns)) {
                continue;
            }

            $remotePath = $targetDir.'/'.$relativePath;

            if ($item->isDir()) {
                if (!$sftp->is_dir($remotePath) && !$sftp->mkdir($remotePath, -1, true)) {
                    throw new SyncException(sprintf('Could not create remote directory: %s', $remotePath));
                }

                continue;
            }

            if (false === $sftp->put($remotePath, $item->getPathname(), SFTP::SOURCE_LOCAL_FILE)) {
                throw new SyncException(sprintf('SFTP upload failed: %s', $remotePath));
            }
        }
    }

    private function ensureLocalDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new SyncException(sprintf('Could not create local directory: %s', $path));
        }
    }

    private function connect(SyncConfig $config, ClientConfig $client): SFTP
    {
        return $this->factory->createSftp($client, $config->sshAgent, $config->forcePassword, $config->strictHostKeyChecking);
    }
}
