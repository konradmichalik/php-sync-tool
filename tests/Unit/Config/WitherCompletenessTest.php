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
use KonradMichalik\SyncTool\Enum\AnonymizationStrategy;
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

    public function testWithClientsPreservesEveryPropertyButTheEndpoints(): void
    {
        $config = self::populatedConfig();

        $this->assertPreserved(
            $config,
            $config->withClients(new ClientConfig(name: 'new-origin'), new ClientConfig(name: 'new-target')),
            ['origin', 'target'],
        );
    }

    /**
     * Guards the guard: every constructor promoted property must be reachable as
     * a public property, otherwise the comparison above would silently skip it.
     */
    public function testEveryConstructorParameterIsAPublicProperty(): void
    {
        foreach ([ClientConfig::class, SyncConfig::class] as $class) {
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
            importFile: '/tmp/import.sql',
            tables: 'pages,content',
            where: 'uid > 10',
            additionalMysqldumpOptions: '--skip-comments',
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
