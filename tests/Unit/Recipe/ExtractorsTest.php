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

use KonradMichalik\SyncTool\Exception\ValidationException;
use KonradMichalik\SyncTool\Recipe\{CredentialValidator, Extractors};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ExtractorsTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ExtractorsTest extends TestCase
{
    #[Test]
    public function typo3FromEnv(): void
    {
        $content = <<<'ENV'
            # database
            TYPO3_CONF_VARS__DB__Connections__Default__dbname=typo3_db
            TYPO3_CONF_VARS__DB__Connections__Default__host=localhost
            TYPO3_CONF_VARS__DB__Connections__Default__user=typo3user
            TYPO3_CONF_VARS__DB__Connections__Default__password=secret
            ENV;

        self::assertSame([
            'name' => 'typo3_db', 'host' => 'localhost', 'user' => 'typo3user',
            'password' => 'secret', 'port' => '3306',
        ], Extractors::typo3FromEnv($content));
    }

    #[Test]
    public function typo3FromAdditional(): void
    {
        $content = <<<'PHP'
            <?php
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'site_db';
            // legacy style
            'dbname' => 'additional_db',
            'host' => 'db.internal',
            'user' => 'webuser',
            'password' => 'p4ss',
            'port' => '3307',
            PHP;

        $result = Extractors::typo3FromAdditional($content);

        self::assertSame('additional_db', $result['name']);
        self::assertSame('db.internal', $result['host']);
        self::assertSame('3307', $result['port']);
    }

    #[Test]
    public function drupalFromSettings(): void
    {
        $content = <<<'PHP'
            <?php
            $databases['default']['default'] = array (
              'database' => 'drupal_db',
              'username' => 'drupal_user',
              'password' => 'drupal_pass',
              'host' => 'localhost',
              'port' => 3307,
            );
            PHP;

        $result = Extractors::drupalFromSettings($content);

        self::assertSame('drupal_db', $result['name']);
        self::assertSame('drupal_user', $result['user']);
        self::assertSame('localhost', $result['host']);
        self::assertSame('3307', $result['port']);
    }

    #[Test]
    public function wordpressFromConfig(): void
    {
        $content = <<<'PHP'
            <?php
            define( 'DB_NAME', 'wordpress' );
            define( 'DB_USER', 'wp_user' );
            define( 'DB_PASSWORD', 'wp_pass' );
            define( 'DB_HOST', 'localhost' );
            PHP;

        self::assertSame([
            'name' => 'wordpress', 'host' => 'localhost', 'user' => 'wp_user',
            'password' => 'wp_pass', 'port' => '3306',
        ], Extractors::wordpressFromConfig($content));
    }

    #[Test]
    public function laravelFromEnvHasNoPortDefault(): void
    {
        $content = <<<'ENV'
            DB_CONNECTION=mysql
            DB_HOST=127.0.0.1
            DB_DATABASE=laravel
            DB_USERNAME=laravel_user
            DB_PASSWORD=laravel_pass
            ENV;

        $result = Extractors::laravelFromEnv($content);

        self::assertSame('laravel', $result['name']);
        self::assertSame('127.0.0.1', $result['host']);
        self::assertSame('laravel_user', $result['user']);
        self::assertSame('', $result['port']);
    }

    #[Test]
    public function symfonyDatabaseUrlLineSkipsComments(): void
    {
        $content = <<<'ENV_WRAP'
        # DATABASE_URL=mysql://commented:out@host:3306/nope
        APP_ENV=prod
        DATABASE_URL=mysql://user:pass@db:3306/app
        ENV_WRAP;

        self::assertSame('DATABASE_URL=mysql://user:pass@db:3306/app', Extractors::symfonyDatabaseUrlLine($content));
    }

    #[Test]
    public function symfonyFromParameters(): void
    {
        $content = <<<'YAML'
            parameters:
                database_host: 127.0.0.1
                database_port: 3306
                database_name: symfony_db
                database_user: sf_user
                database_password: sf_pass
            YAML;

        $result = Extractors::symfonyFromParameters($content);

        self::assertSame('symfony_db', $result['name']);
        self::assertSame('sf_user', $result['user']);
        self::assertSame('127.0.0.1', $result['host']);
    }

    #[Test]
    public function credentialValidatorAcceptsCompleteCredentials(): void
    {
        (new CredentialValidator())->validate('origin', [
            'name' => 'db', 'host' => 'localhost', 'user' => 'u', 'password' => 'p',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function credentialValidatorRejectsMissingField(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing database credential "password" for target client');

        (new CredentialValidator())->validate('target', [
            'name' => 'db', 'host' => 'localhost', 'user' => 'u', 'password' => '',
        ]);
    }

    #[Test]
    public function symfonyDatabaseUrlLineReturnsEmptyWhenAbsent(): void
    {
        self::assertSame('', Extractors::symfonyDatabaseUrlLine("# DATABASE_URL is commented\nAPP_ENV=prod"));
    }
}
