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

namespace KonradMichalik\SyncTool\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

use function sprintf;

/**
 * SyncScenarioTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncScenarioTest extends TestCase
{
    private const COMPOSE_DIR = __DIR__.'/../../docker';

    protected function setUp(): void
    {
        if (!$this->isStackRunning()) {
            self::markTestSkipped('Docker stack is not running. Start it with: cd docker && docker compose up -d --build');
        }
    }

    #[Test]
    public function receiverSyncCopiesAllRowsFromOriginToTarget(): void
    {
        $this->resetDatabases();

        self::assertSame(3, $this->rowCount('db1'), 'origin should start with 3 rows');
        self::assertSame(0, $this->rowCount('db2'), 'target should start empty');

        $result = $this->compose([
            'exec', '-T', 'www2',
            'php', '/app/bin/sync-tool', '-f', '/app/docker/configs/receiver.yaml', '-y',
        ]);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'), 'target should hold the origin rows after sync');
    }

    #[Test]
    public function senderSyncPushesAllRowsToRemoteTarget(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www1', 'sender.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function proxySyncTransfersBetweenTwoRemoteHosts(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('proxy', 'proxy.yaml');

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function receiverSyncViaSftpFallbackCopiesAllRows(): void
    {
        $this->resetDatabases();

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--no-rsync']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
    }

    #[Test]
    public function clearDatabaseDropsOrphanTablesBeforeImport(): void
    {
        $this->resetDatabases();
        $this->mysql('db2', 'CREATE TABLE orphan (id INT);');
        self::assertSame(1, $this->tableCount('db2', 'orphan'), 'orphan table should exist before sync');

        $result = $this->runSyncTool('www2', 'receiver.yaml', ['--clear-database']);

        self::assertTrue($result->isSuccessful(), $result->getErrorOutput().$result->getOutput());
        self::assertSame(3, $this->rowCount('db2'));
        self::assertSame(0, $this->tableCount('db2', 'orphan'), 'orphan table should be dropped by --clear-database');
    }

    private function resetDatabases(): void
    {
        $this->mysql('db1', 'DROP TABLE IF EXISTS person; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255)); INSERT INTO person (name) VALUES ("Alice"),("Bob"),("Carol");');
        $this->mysql('db2', 'DROP TABLE IF EXISTS person; CREATE TABLE person (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255));');
    }

    private function rowCount(string $dbService): int
    {
        $output = $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', 'SELECT COUNT(*) FROM person;'])->getOutput();

        return (int) trim($output);
    }

    private function mysql(string $dbService, string $sql): void
    {
        $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-e', $sql]);
    }

    private function tableCount(string $dbService, string $table): int
    {
        $output = $this->compose(['exec', '-T', $dbService, 'mariadb', '-udb', '-pdb', 'db', '-N', '-e', sprintf("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db' AND table_name='%s';", $table)])->getOutput();

        return (int) trim($output);
    }

    /**
     * @param list<string> $extraArgs
     */
    private function runSyncTool(string $node, string $configFile, array $extraArgs = []): Process
    {
        return $this->compose([
            'exec', '-T', $node,
            'php', '/app/bin/sync-tool', '-f', '/app/docker/configs/'.$configFile, '-y',
            ...$extraArgs,
        ]);
    }

    private function isStackRunning(): bool
    {
        $process = $this->compose(['ps', '--status', 'running', '--services']);

        return $process->isSuccessful() && str_contains($process->getOutput(), 'www2');
    }

    /**
     * @param list<string> $arguments
     */
    private function compose(array $arguments): Process
    {
        $process = new Process(['docker', 'compose', ...$arguments], self::COMPOSE_DIR);
        $process->setTimeout(120.0);
        $process->run();

        return $process;
    }
}
