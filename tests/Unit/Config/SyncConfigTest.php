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

namespace KonradMichalik\SyncTool\Tests\Unit\Config;

use KonradMichalik\SyncTool\Config\{ClientConfig, FileTransferConfig, SyncConfig};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

use function sprintf;

/**
 * SyncConfigTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SyncConfigTest extends TestCase
{
    #[Test]
    public function defaultsMatchPython(): void
    {
        $config = SyncConfig::fromArray([]);

        self::assertTrue($config->checkDump);
        self::assertTrue($config->useRsync);
        self::assertFalse($config->verbose);
        self::assertFalse($config->withFiles);
        self::assertSame('', $config->dumpName);
        self::assertSame([], $config->files);
        self::assertSame(22, $config->origin->port);
    }

    #[Test]
    public function importFileMapsFromImportKey(): void
    {
        $config = SyncConfig::fromArray(['import' => '/tmp/dump.sql.gz']);

        self::assertSame('/tmp/dump.sql.gz', $config->importFile);
    }

    #[Test]
    public function sshPasswordIsReadFromNestedDict(): void
    {
        $config = SyncConfig::fromArray([
            'ssh_password' => ['origin' => 'o-secret', 'target' => 't-secret'],
        ]);

        self::assertSame('o-secret', $config->sshPasswordOrigin);
        self::assertSame('t-secret', $config->sshPasswordTarget);
    }

    #[Test]
    public function ignoreTablesFallBackToSingularKey(): void
    {
        $config = SyncConfig::fromArray(['ignore_table' => ['cache', 'sessions']]);

        self::assertSame(['cache', 'sessions'], $config->ignoreTables);
    }

    #[Test]
    public function filesNewFlatListFormat(): void
    {
        $config = SyncConfig::fromArray([
            'files' => [
                ['origin' => 'fileadmin/', 'target' => 'fileadmin/', 'exclude' => ['*.log']],
            ],
        ]);

        self::assertCount(1, $config->files);
        self::assertSame('fileadmin/', $config->files[0]->origin);
        self::assertSame(['*.log'], $config->files[0]->exclude);
    }

    #[Test]
    public function filesLegacyNestedFormat(): void
    {
        $config = SyncConfig::fromArray([
            'files' => [
                'config' => [
                    ['origin' => 'fileadmin/', 'target' => 'fileadmin/'],
                ],
                'option' => ['--verbose', '--compress'],
            ],
        ]);

        self::assertCount(1, $config->files);
        self::assertSame('fileadmin/', $config->files[0]->origin);
        self::assertSame('--verbose --compress', $config->filesOptions);
    }

    #[Test]
    public function directFilesOptionsTakePrecedenceOverLegacy(): void
    {
        $config = SyncConfig::fromArray([
            'files' => ['option' => ['--legacy']],
            'files_options' => '--direct',
        ]);

        self::assertSame('--direct', $config->filesOptions);
    }

    #[Test]
    public function getClientReturnsOriginAndTarget(): void
    {
        $config = SyncConfig::fromArray([
            'origin' => ['host' => 'origin.example.com'],
            'target' => ['host' => 'target.example.com'],
        ]);

        self::assertSame('origin.example.com', $config->getClient('origin')->host);
        self::assertSame('target.example.com', $config->getClient('target')->host);
    }

    #[Test]
    public function strictHostKeyCheckingDefaultsTrueAndReadsFlag(): void
    {
        self::assertTrue(SyncConfig::fromArray([])->strictHostKeyChecking);
        self::assertFalse(SyncConfig::fromArray(['ssh_strict_host_key_checking' => false])->strictHostKeyChecking);
    }

    #[Test]
    public function withClientsReplacesOriginAndTargetOnly(): void
    {
        $config = SyncConfig::fromArray(['yes' => true, 'origin' => ['host' => 'o'], 'target' => ['host' => 't']]);
        $next = $config->withClients(ClientConfig::fromArray(['host' => 'o2']), ClientConfig::fromArray(['host' => 't2']));

        self::assertSame('o2', $next->origin->host);
        self::assertSame('t2', $next->target->host);
        self::assertTrue($next->yes, 'other config is preserved');
    }

    /**
     * Guards against the brittleness of withClients() re-listing every constructor
     * field: a forgotten field would silently reset to its default. Every non-client
     * property must survive the copy unchanged.
     */
    /**
     * The option is not MySQL-only any more, but a configuration written for the
     * Python tool still spells it the old way.
     */
    #[Test]
    public function theDumpOptionsAreReadFromEitherSpelling(): void
    {
        self::assertSame(
            '--skip-comments',
            SyncConfig::fromArray(['additional_dump_options' => '--skip-comments'])->additionalDumpOptions,
        );
        self::assertSame(
            '--skip-lock-tables',
            SyncConfig::fromArray(['additional_mysqldump_options' => '--skip-lock-tables'])->additionalDumpOptions,
        );
        self::assertSame(
            '--new',
            SyncConfig::fromArray([
                'additional_dump_options' => '--new',
                'additional_mysqldump_options' => '--old',
            ])->additionalDumpOptions,
            'the current spelling wins',
        );
    }

    #[Test]
    public function withClientsPreservesEveryNonClientProperty(): void
    {
        $config = SyncConfig::fromArray([
            'verbose' => true, 'mute' => true, 'dry_run' => true, 'yes' => true,
            'reverse' => true, 'keep_dump' => true, 'dump_name' => 'd.sql',
            'check_dump' => false, 'clear_database' => true, 'import' => 'in.sql.gz',
            'tables' => 'a,b', 'where' => 'id>0', 'additional_dump_options' => '--x',
            'ignore_tables' => ['cache'], 'truncate_tables' => ['log'], 'use_rsync' => false,
            'use_rsync_options' => '-z', 'use_sshpass' => true, 'with_files' => true,
            'files_only' => true, 'ssh_agent' => true, 'force_password' => true,
            'ssh_strict_host_key_checking' => false, 'ssh_password' => ['origin' => 'po', 'target' => 'pt'],
            'config_file_path' => '/c.yaml', 'log_file' => '/l.log', 'json_log' => true,
            'type' => 'symfony', 'scripts' => ['before' => 'echo hi'],
        ]);

        $next = $config->withClients(new ClientConfig(host: 'o2'), new ClientConfig(host: 't2'));

        foreach ((new ReflectionObject($config))->getProperties() as $property) {
            $name = $property->getName();
            if ('origin' === $name || 'target' === $name) {
                continue;
            }

            self::assertEquals(
                $property->getValue($config),
                $property->getValue($next),
                sprintf('withClients() must preserve property "%s"', $name),
            );
        }
    }

    #[Test]
    public function withClientsCarriesEverySettingExceptTheClients(): void
    {
        $original = new SyncConfig(
            verbose: true,
            mute: true,
            dryRun: true,
            yes: true,
            reverse: true,
            keepDump: true,
            dumpName: 'fixture.sql',
            checkDump: false,
            clearDatabase: true,
            importFile: '/tmp/fixture.sql.gz',
            tables: 'users,orders',
            where: 'id > 1',
            additionalDumpOptions: '--skip-lock-tables',
            ignoreTables: ['cache_pages'],
            truncateTables: ['sys_log'],
            backupBeforeImport: true,
            useRsync: false,
            useRsyncOptions: '--archive',
            useSshpass: true,
            files: [new FileTransferConfig('fileadmin', 'fileadmin')],
            filesOptions: '--delete',
            withFiles: true,
            filesOnly: true,
            sshAgent: true,
            forcePassword: true,
            strictHostKeyChecking: false,
            sshPasswordOrigin: 'origin-secret',
            sshPasswordTarget: 'target-secret',
            configFilePath: '/tmp/config.yaml',
            logFile: '/tmp/sync.log',
            jsonLog: true,
            type: 'TYPO3',
            scripts: ['before' => 'echo one'],
            origin: new ClientConfig(host: 'old-origin'),
            target: new ClientConfig(host: 'old-target'),
        );

        $copy = $original->withClients(
            new ClientConfig(host: 'new-origin'),
            new ClientConfig(host: 'new-target'),
        );

        self::assertSame('new-origin', $copy->origin->host);
        self::assertSame('new-target', $copy->target->host);

        $constructor = (new ReflectionClass(SyncConfig::class))->getConstructor();
        self::assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if ('origin' === $name || 'target' === $name) {
                continue;
            }

            self::assertEquals(
                $original->{$name},
                $copy->{$name},
                sprintf('withClients() dropped "%s"', $name),
            );

            if ($parameter->isDefaultValueAvailable()) {
                self::assertNotEquals(
                    $parameter->getDefaultValue(),
                    $original->{$name},
                    sprintf('the fixture value for "%s" equals its default, so this test cannot detect a drop', $name),
                );
            }
        }
    }
}
