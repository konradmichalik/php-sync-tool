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
    public function acceptsJumpHostObjectAndLink(): void
    {
        $this->expectNotToPerformAssertions();
        (new ConfigValidator())->validate(['origin' => ['link' => '@prod', 'jump_host' => ['host' => 'bastion', 'port' => 22]]]);
    }

    #[Test]
    public function rejectsNonObjectJumpHost(): void
    {
        $this->expectException(ValidationException::class);
        (new ConfigValidator())->validate(['origin' => ['jump_host' => 'bastion']]);
    }

    #[Test]
    public function rejectsNonBooleanStrictHostKeyChecking(): void
    {
        $this->expectException(ValidationException::class);
        (new ConfigValidator())->validate(['ssh_strict_host_key_checking' => 'yes']);
    }

    #[Test]
    public function acceptsAKnownDatabaseType(): void
    {
        $this->expectNotToPerformAssertions();

        (new ConfigValidator())->validate([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'postgres']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
        ]);
    }

    #[Test]
    public function rejectsAnUnknownDatabaseType(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfigValidator())->validate([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app', 'type' => 'oracle']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
        ]);
    }

    #[Test]
    public function acceptsAnAnonymizationBlockOnTheTarget(): void
    {
        $this->expectNotToPerformAssertions();

        (new ConfigValidator())->validate([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app']],
            'target' => [
                'path' => '/t',
                'db' => ['name' => 'app'],
                'anonymize' => [
                    'fe_users' => ['email' => 'email', 'name' => ['strategy' => 'static', 'value' => 'Redacted']],
                ],
            ],
        ]);
    }

    #[Test]
    public function rejectsAnUnknownAnonymizationStrategy(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfigValidator())->validate([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app'], 'anonymize' => ['fe_users' => ['email' => 'shuffle']]],
        ]);
    }

    #[Test]
    public function rejectsAnonymizationOnTheOrigin(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('#only supported on the target#');

        (new ConfigValidator())->validate([
            'origin' => ['path' => '/o', 'db' => ['name' => 'app'], 'anonymize' => ['fe_users' => ['email' => 'email']]],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
        ]);
    }

    #[Test]
    public function acceptsALocalEndpointBlock(): void
    {
        $this->expectNotToPerformAssertions();

        (new ConfigValidator())->validate([
            'local' => ['path' => 'web/typo3conf/LocalConfiguration.php', 'dump_dir' => 'var/transfer/'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
        ]);
    }

    #[Test]
    public function rejectsAMistypedKeyInTheLocalEndpointBlock(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfigValidator())->validate([
            'local' => ['port' => 'not-a-number'],
            'origin' => ['path' => '/o', 'db' => ['name' => 'app']],
            'target' => ['path' => '/t', 'db' => ['name' => 'app']],
        ]);
    }

    /**
     * A misspelled key used to validate happily and then do nothing at all, which
     * is the worst possible outcome for a key like this one.
     */
    #[Test]
    public function rejectsAnUnknownRootKey(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('#ignore_tabel#');

        (new ConfigValidator())->validate(['ignore_tabel' => ['cache_pages']]);
    }

    #[Test]
    public function rejectsAnUnknownEndpointKey(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('#dump_directory#');

        (new ConfigValidator())->validate(['origin' => ['dump_directory' => '/tmp/']]);
    }

    #[Test]
    public function rejectsAnUnknownDatabaseKey(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfigValidator())->validate(['origin' => ['db' => ['dbname' => 'app']]]);
    }

    /**
     * YAML anchors need somewhere to live, and a config author may want a note of
     * their own, so keys starting with `x` or `.` are none of our business.
     */
    #[Test]
    public function acceptsExtensionAndAnchorKeys(): void
    {
        $this->expectNotToPerformAssertions();

        (new ConfigValidator())->validate([
            '.defaults' => ['user' => 'deploy'],
            'x-note' => 'whatever the author needs',
            'origin' => ['path' => '/o', '.anchor' => ['a' => 'b'], 'x-owner' => 'team'],
            'target' => ['path' => '/t'],
        ]);
    }

    /**
     * `script` is the spelling every example in the documentation uses.
     */
    #[Test]
    public function acceptsBothLifecycleScriptSpellings(): void
    {
        $this->expectNotToPerformAssertions();

        (new ConfigValidator())->validate([
            'script' => ['before' => 'echo root-before'],
            'origin' => ['script' => ['before' => 'echo origin-before']],
            'target' => ['scripts' => ['after' => 'echo target-after']],
        ]);
    }

    #[Test]
    public function rejectsAnUnknownLifecyclePhase(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfigValidator())->validate(['target' => ['script' => ['beforre' => 'echo typo']]]);
    }
}
