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

use KonradMichalik\SyncTool\Config\ConfigValidator;
use KonradMichalik\SyncTool\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ConfigValidatorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigValidator();
    }

    #[Test]
    public function emptyConfigIsValid(): void
    {
        $this->validator->validate([]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function fullValidConfigPasses(): void
    {
        $this->validator->validate([
            'type' => 'TYPO3',
            'origin' => [
                'host' => 'origin.example.com',
                'user' => 'admin',
                'port' => 22,
                'db' => ['name' => 'db', 'port' => 3306],
            ],
            'target' => ['path' => '/var/www'],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function invalidFrameworkTypeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['type' => 'Joomla']);
    }

    #[Test]
    public function nonNumericPortIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(['origin' => ['port' => 'twenty-two']]);
    }

    #[Test]
    public function malformedHostnamePassesBecauseFormatIsNotEnforced(): void
    {
        $this->validator->validate(['origin' => ['host' => 'not a valid host!!']]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function acceptsBooleanSslDisabled(): void
    {
        $this->expectNotToPerformAssertions();
        (new ConfigValidator())->validate(['origin' => ['db' => ['name' => 'd', 'ssl_disabled' => true]]]);
    }

    #[Test]
    public function rejectsNonBooleanSslDisabled(): void
    {
        $this->expectException(ValidationException::class);
        (new ConfigValidator())->validate(['origin' => ['db' => ['name' => 'd', 'ssl_disabled' => 'yes']]]);
    }

    #[Test]
    public function rejectsNonBooleanStrictHostKeyChecking(): void
    {
        $this->expectException(ValidationException::class);
        (new ConfigValidator())->validate(['ssh_strict_host_key_checking' => 'yes']);
    }
}
