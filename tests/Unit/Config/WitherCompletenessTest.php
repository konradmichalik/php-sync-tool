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

use KonradMichalik\SyncTool\Config\{AnonymizationRule, ClientConfig, DatabaseConfig, FileTransferConfig, JumpHostConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\{AnonymizationStrategy, DatabaseSystem};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function in_array;
use function sprintf;

/**
 * WitherCompletenessTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
#[CoversClass(ClientConfig::class)]
#[CoversClass(DatabaseConfig::class)]
#[CoversClass(SyncConfig::class)]
final class WitherCompletenessTest extends TestCase
{
    public function testWithDbPreservesEveryPropertyButTheDatabase(): void
    {
        $client = self::populatedClient();

        $this->assertPreserved(
            $client,
            $client->withDb(new DatabaseConfig(name: 'replaced', user: 'replaced')),
            ['db'],
        );
    }

    public function testWithPasswordPreservesEveryPropertyButThePassword(): void
    {
        $client = self::populatedClient();

        $this->assertPreserved(
            $client,
            $client->withPassword('typed-at-the-prompt'),
            ['password'],
        );
    }

    public function testOverriddenByCarriesEveryConfiguredPropertyOver(): void
    {
        $explicit = self::populatedDatabase();

        // `type` is the documented exception: an unset type is indistinguishable
        // from an explicit `mysql`, so the detected driver stays authoritative.
        $this->assertPreserved(
            $explicit,
            (new DatabaseConfig())->overriddenBy($explicit),
            ['type'],
        );
    }

    public function testWithClientsPreservesEveryPropertyButTheEndpoints(): void
    {
        $config = self::populatedConfig();

        $this->assertPreserved(
            $config,
            $config->withClients(new ClientConfig(name: 'new-origin'), new ClientConfig(name: 'new-target')),
            ['origin', 'target'],
        );
    }

    public function testWithSshAgentPreservesEveryPropertyButTheFlag(): void
    {
        $config = self::populatedConfig();

        $this->assertPreserved(
            $config,
            $config->withSshAgent(!$config->sshAgent),
            ['sshAgent'],
        );
    }

    /**
     * Guards the guard: a fixture value that happens to equal the constructor
     * default makes every preservation check above vacuous for that property. A
     * wither dropping it would produce the same default, the comparison would
     * match, and the test would pass while the property was lost.
     *
     * This caught `backupBeforeImport`, which the fixture left at `false`.
     */
    public function testEveryFixturePropertyDiffersFromItsDefault(): void
    {
        foreach ([new ClientConfig(), new DatabaseConfig(), new SyncConfig()] as $default) {
            $populated = match (true) {
                $default instanceof ClientConfig => self::populatedClient(),
                $default instanceof DatabaseConfig => self::populatedDatabase(),
                default => self::populatedConfig(),
            };

            foreach (get_object_vars($populated) as $property => $value) {
                self::assertNotEquals(
                    $default->{$property},
                    $value,
                    sprintf('%s::$%s is left at its default in the fixture, so no preservation test can see it go missing', $default::class, $property),
                );
            }
        }
    }

    /**
     * Guards the guard: every constructor promoted property must be reachable as
     * a public property, otherwise the comparison above would silently skip it.
     */
    public function testEveryConstructorParameterIsAPublicProperty(): void
    {
        foreach ([ClientConfig::class, DatabaseConfig::class, SyncConfig::class] as $class) {
            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);

            foreach ($constructor->getParameters() as $parameter) {
                self::assertTrue(
                    $reflection->hasProperty($parameter->getName()),
                    sprintf('%s::$%s is a constructor parameter but not a property', $class, $parameter->getName()),
                );
            }
        }
    }

    /**
     * @param list<string> $replaced names the wither is expected to change
     */
    private function assertPreserved(object $before, object $after, array $replaced): void
    {
        $reflection = new ReflectionClass($before);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $checked = 0;

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (in_array($name, $replaced, true)) {
                self::assertNotEquals(
                    $before->{$name},
                    $after->{$name},
                    sprintf('%s::$%s should have been replaced', $reflection->getShortName(), $name),
                );

                continue;
            }

            self::assertEquals(
                $before->{$name},
                $after->{$name},
                sprintf(
                    '%s::$%s was dropped by the wither. Pass it through explicitly.',
                    $reflection->getShortName(),
                    $name,
                ),
            );
            ++$checked;
        }

        self::assertGreaterThan(0, $checked, 'No properties were compared, the guard is not working');
    }

    /**
     * Every property set to something distinguishable from its default, so a
     * dropped field shows up as a difference rather than an accidental match.
     */
    private static function populatedDatabase(): DatabaseConfig
    {
        return new DatabaseConfig(
            name: 'app',
            host: '127.0.0.1',
            user: 'deploy',
            password: 'secret',
            port: 6543,
            sslDisabled: true,
            sslSkipVerify: true,
            sslCa: '/etc/ssl/ca.pem',
            sslCapath: '/etc/ssl/certs',
            sslCert: '/etc/ssl/client.pem',
            sslKey: '/etc/ssl/client.key',
            sslCipher: 'TLS_AES_256_GCM_SHA384',
            type: DatabaseSystem::PostgreSQL,
        );
    }

    private static function populatedClient(): ClientConfig
    {
        return new ClientConfig(
            path: '/var/www/config/system/settings.php',
            name: 'production',
            host: 'example.com',
            user: 'deploy',
            password: 'secret',
            sshKey: '/home/deploy/.ssh/id_ed25519',
            port: 2222,
            dumpDir: '/var/backups/',
            keepDumps: 7,
            db: new DatabaseConfig(name: 'original', user: 'original'),
            jumpHost: new JumpHostConfig(host: 'jump.example.com', user: 'jump'),
            afterDump: '/var/backups/extra.sql',
            postSql: ['UPDATE pages SET hidden = 1;'],
            console: ['php' => '/usr/bin/php8.3'],
            scripts: ['before' => 'echo before'],
            protect: true,
            link: 'https://example.com',
            anonymize: [
                new AnonymizationRule('fe_users', 'email', AnonymizationStrategy::Email),
            ],
        );
    }

    private static function populatedConfig(): SyncConfig
    {
        return new SyncConfig(
            verbose: true,
            mute: true,
            dryRun: true,
            yes: true,
            reverse: true,
            keepDump: true,
            dumpName: 'custom-dump',
            checkDump: false,
            clearDatabase: true,
            backupBeforeImport: true,
            importFile: '/tmp/import.sql',
            tables: 'pages,content',
            where: 'uid > 10',
            additionalDumpOptions: '--skip-comments',
            ignoreTables: ['cache_*'],
            truncateTables: ['sessions'],
            useRsync: false,
            useRsyncOptions: '--partial',
            useSshpass: true,
            files: [new FileTransferConfig('fileadmin', 'fileadmin')],
            filesOptions: '--dry-run',
            withFiles: true,
            filesOnly: true,
            sshAgent: true,
            forcePassword: true,
            strictHostKeyChecking: false,
            sshPasswordOrigin: 'origin-secret',
            sshPasswordTarget: 'target-secret',
            configFilePath: '/etc/sync.yaml',
            logFile: '/var/log/sync.log',
            jsonLog: true,
            type: 'TYPO3',
            scripts: ['after' => 'echo after'],
            origin: new ClientConfig(name: 'original-origin'),
            target: new ClientConfig(name: 'original-target'),
        );
    }
}
