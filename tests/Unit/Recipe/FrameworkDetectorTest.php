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

namespace MoveElevator\DbSyncTool\Tests\Unit\Recipe;

use MoveElevator\DbSyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
use MoveElevator\DbSyncTool\Enum\Framework;
use MoveElevator\DbSyncTool\Recipe\FrameworkDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;


/**
 * FrameworkDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */

final class FrameworkDetectorTest extends TestCase
{
    private FrameworkDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new FrameworkDetector();
    }

    #[Test]
    public function detectsTypo3FromLocalConfiguration(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/typo3conf/LocalConfiguration.php'));

        self::assertSame(Framework::Typo3, $this->detector->detect($config));
    }

    #[Test]
    public function settingsPhpInSystemPathIsTypo3NotDrupal(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/config/system/settings.php'));

        self::assertSame(Framework::Typo3, $this->detector->detect($config));
    }

    #[Test]
    public function plainSettingsPhpIsDrupal(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/sites/default/settings.php'));

        self::assertSame(Framework::Drupal, $this->detector->detect($config));
    }

    #[Test]
    public function bareEnvResolvesToLaravelBecauseLastMatchWins(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/.env'));

        self::assertSame(Framework::Laravel, $this->detector->detect($config));
    }

    #[Test]
    public function detectsWordPress(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/wp-config.php'));

        self::assertSame(Framework::WordPress, $this->detector->detect($config));
    }

    #[Test]
    public function skipsWhenTypeAlreadySet(): void
    {
        $config = new SyncConfig(type: 'TYPO3', origin: new ClientConfig(path: '/var/www/wp-config.php'));

        self::assertNull($this->detector->detect($config));
    }

    #[Test]
    public function skipsWhenManualDatabaseConfigured(): void
    {
        $config = new SyncConfig(
            origin: new ClientConfig(path: '/var/www/wp-config.php', db: new DatabaseConfig(name: 'manual')),
        );

        self::assertNull($this->detector->detect($config));
    }

    #[Test]
    public function returnsNullWhenNoPathMatches(): void
    {
        $config = new SyncConfig(origin: new ClientConfig(path: '/var/www/unknown.txt'));

        self::assertNull($this->detector->detect($config));
    }
}
