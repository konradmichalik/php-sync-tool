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

namespace KonradMichalik\SyncTool\Tests\Unit\Recipe;

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\Framework;
use KonradMichalik\SyncTool\Recipe\CredentialResolver;
use KonradMichalik\SyncTool\Tests\Fixture\RecordingCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CredentialResolverTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class CredentialResolverTest extends TestCase
{
    #[Test]
    public function resolveReadsTypo3ViaPhpInclude(): void
    {
        $config = SyncConfig::fromArray(['type' => 'typo3', 'origin' => ['path' => '/app/LocalConfiguration.php']]);
        $json = json_encode(['DB' => ['Connections' => ['Default' => [
            'dbname' => 'typo3_db', 'host' => 'db', 'user' => 'u', 'password' => 'p', 'port' => 3306,
        ]]]], \JSON_THROW_ON_ERROR);
        $runner = new RecordingCommandRunner(['LocalConfiguration.php' => $json]);

        $db = (new CredentialResolver())->resolve($config, $config->origin, $runner);

        self::assertNotNull($db);
        self::assertSame('typo3_db', $db->name);
        self::assertTrue($runner->ran('php -r'), 'reads the file through a PHP include');
    }

    #[Test]
    public function resolveDetectsFrameworkFromPathWhenTypeAbsent(): void
    {
        $config = SyncConfig::fromArray(['origin' => ['path' => '/app/wp-config.php']]);
        $runner = new RecordingCommandRunner(['cat ' => "<?php\ndefine('DB_NAME', 'detected');\ndefine('DB_USER', 'wp');\ndefine('DB_PASSWORD', 's');\ndefine('DB_HOST', 'localhost');"]);

        $db = (new CredentialResolver())->resolve($config, $config->origin, $runner);

        self::assertNotNull($db);
        self::assertSame('detected', $db->name);
    }

    #[Test]
    public function resolveFallsBackToDrushWhenDrupalSettingsAreEmpty(): void
    {
        $config = SyncConfig::fromArray(['type' => 'drupal', 'origin' => ['path' => '/app/settings.php']]);
        $drush = json_encode([
            'db-name' => 'drupal_db', 'db-hostname' => 'db', 'db-username' => 'u', 'db-password' => 'p', 'db-port' => '3306',
        ], \JSON_THROW_ON_ERROR);
        $runner = new RecordingCommandRunner(['core-status' => $drush]);

        $db = (new CredentialResolver())->resolve($config, $config->origin, $runner);

        self::assertNotNull($db);
        self::assertSame('drupal_db', $db->name);
        self::assertTrue($runner->ran('drush core-status'), 'falls back to a drush query');
    }

    #[Test]
    public function resolveReturnsNullWhenNoFrameworkDetected(): void
    {
        $config = SyncConfig::fromArray(['origin' => ['path' => '/app/unknown.txt']]);

        self::assertNull((new CredentialResolver())->resolve($config, $config->origin, new RecordingCommandRunner()));
    }

    #[Test]
    public function extractTypo3AdditionalConfiguration(): void
    {
        $content = <<<'PHP'
            <?php
            return [
                'dbname' => 'add_db',
                'user' => 'add_user',
            ];
            PHP;

        $result = CredentialResolver::extract(Framework::Typo3, 'AdditionalConfiguration.php', $content);

        self::assertSame('add_db', $result['name']);
        self::assertSame('add_user', $result['user']);
    }

    #[Test]
    public function extractReturnsEmptyOnUnparsableContent(): void
    {
        $typo3 = CredentialResolver::extract(Framework::Typo3, 'LocalConfiguration.php', 'not-json');
        $drush = CredentialResolver::extract(Framework::Drupal, '__drush__', 'not-json');

        self::assertSame('', $typo3['name']);
        self::assertSame('', $drush['name']);
    }

    #[Test]
    public function extractWordPressConfig(): void
    {
        $content = <<<'PHP'
            <?php
            define( 'DB_NAME', 'wp' );
            define( 'DB_USER', 'root' );
            define( 'DB_PASSWORD', 'secret' );
            define( 'DB_HOST', 'localhost' );
            PHP;

        $result = CredentialResolver::extract(Framework::WordPress, 'wp-config.php', $content);

        self::assertSame('wp', $result['name']);
        self::assertSame('root', $result['user']);
        self::assertSame('secret', $result['password']);
        self::assertSame('localhost', $result['host']);
    }

    #[Test]
    public function extractLaravelEnv(): void
    {
        $content = <<<'ENV'
            DB_CONNECTION=mysql
            DB_HOST=127.0.0.1
            DB_DATABASE=laravel_db
            DB_USERNAME=laravel_user
            DB_PASSWORD=laravel_pass
            DB_PORT=3306
            ENV;

        $result = CredentialResolver::extract(Framework::Laravel, '.env', $content);

        self::assertSame('laravel_db', $result['name']);
        self::assertSame('laravel_user', $result['user']);
        self::assertSame('laravel_pass', $result['password']);
        self::assertSame('127.0.0.1', $result['host']);
    }

    #[Test]
    public function extractSymfonyDatabaseUrl(): void
    {
        $content = 'DATABASE_URL=mysql://u:p@h:3306/db';

        $result = CredentialResolver::extract(Framework::Symfony, '.env', $content);

        self::assertSame('db', $result['name']);
        self::assertSame('u', $result['user']);
        self::assertSame('p', $result['password']);
        self::assertSame('h', $result['host']);
        self::assertSame('3306', $result['port']);
    }

    #[Test]
    public function extractSymfonyParameters(): void
    {
        $content = <<<'YAML'
            parameters:
                database_host: 127.0.0.1
                database_port: 3306
                database_name: sf_db
                database_user: sf_user
                database_password: sf_pass
            YAML;

        $result = CredentialResolver::extract(Framework::Symfony, 'parameters.yml', $content);

        self::assertSame('sf_db', $result['name']);
        self::assertSame('sf_user', $result['user']);
    }

    #[Test]
    public function extractTypo3Env(): void
    {
        $content = <<<'ENV'
            TYPO3_CONF_VARS__DB__Connections__Default__dbname=typo3_db
            TYPO3_CONF_VARS__DB__Connections__Default__host=db.local
            TYPO3_CONF_VARS__DB__Connections__Default__user=t3user
            TYPO3_CONF_VARS__DB__Connections__Default__password=t3pass
            ENV;

        $result = CredentialResolver::extract(Framework::Typo3, '.env', $content);

        self::assertSame('typo3_db', $result['name']);
        self::assertSame('t3user', $result['user']);
    }

    #[Test]
    public function extractTypo3LocalConfigurationJsonOutput(): void
    {
        $data = ['DB' => ['Connections' => ['Default' => [
            'dbname' => 'typo3_local', 'host' => 'localhost',
            'user' => 'typo3', 'password' => 'pass123',
        ]]]];
        $content = json_encode(['DB' => $data['DB']], \JSON_THROW_ON_ERROR);

        $result = CredentialResolver::extract(Framework::Typo3, 'LocalConfiguration.php', $content);

        self::assertSame('typo3_local', $result['name']);
        self::assertSame('typo3', $result['user']);
    }

    #[Test]
    public function extractDrupalSettings(): void
    {
        $content = <<<'PHP'
            <?php
            $databases['default']['default'] = array (
              'database' => 'drupal_db',
              'username' => 'drupal_user',
              'password' => 'drupal_pass',
              'host' => 'localhost',
              'port' => 3306,
            );
            PHP;

        $result = CredentialResolver::extract(Framework::Drupal, 'settings.php', $content);

        self::assertSame('drupal_db', $result['name']);
        self::assertSame('drupal_user', $result['user']);
    }

    #[Test]
    public function extractDrupalDrushJson(): void
    {
        $drushData = [
            'db-name' => 'drupal_drush',
            'db-hostname' => 'db.local',
            'db-username' => 'drush_user',
            'db-password' => 'drush_pass',
            'db-port' => '3306',
        ];
        $content = json_encode($drushData, \JSON_THROW_ON_ERROR);

        $result = CredentialResolver::extract(Framework::Drupal, '__drush__', $content);

        self::assertSame('drupal_drush', $result['name']);
        self::assertSame('drush_user', $result['user']);
    }
}
