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

namespace KonradMichalik\SyncTool\Tests\Unit\Command;

use KonradMichalik\SyncTool\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * AnonymizeCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class AnonymizeCommandTest extends TestCase
{
    private const RULES = "  anonymize:\n    account:\n      email: email\n";

    private string $dir;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir().'/db-sync-anonymize-'.uniqid();
        mkdir($dir);
        $this->dir = $dir;
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    /**
     * Masking is irreversible, and a remote target is most often a live system.
     */
    #[Test]
    public function aRemoteTargetIsRefused(): void
    {
        $tester = $this->anonymize("target:\n  host: prod.example.com\n  user: deploy\n  db: {name: db, user: r, password: r}\n".self::RULES);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('only anonymizes a local target', $tester->getDisplay());
    }

    #[Test]
    public function aTargetWithoutRulesIsRefused(): void
    {
        $tester = $this->anonymize("target:\n  db: {name: db, user: r, password: r}\n");

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No anonymize rules configured for the target', $tester->getDisplay());
    }

    #[Test]
    public function aProtectedTargetIsRefused(): void
    {
        $tester = $this->anonymize("target:\n  protect: true\n  db: {name: db, user: r, password: r}\n".self::RULES);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('protected', $tester->getDisplay());
    }

    #[Test]
    public function decliningTheConfirmationChangesNothing(): void
    {
        $tester = $this->anonymize("target:\n  db: {name: shop, user: r, password: r}\n".self::RULES, ['no'], ['--yes' => false]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('This irreversibly anonymizes the local database "shop". Continue?', $tester->getDisplay());
        self::assertStringContainsString('Aborted by user', $tester->getDisplay());
    }

    #[Test]
    public function aDryRunChangesNothing(): void
    {
        $tester = $this->anonymize("target:\n  db: {name: db, user: r, password: r}\n".self::RULES, [], ['--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Dry run', $tester->getDisplay());
    }

    /**
     * An inherited sync option that is silently ignored, like `--tables`, would
     * suggest a narrower masking than the one that actually runs.
     */
    #[Test]
    public function aSyncOnlyOptionIsRefused(): void
    {
        $tester = $this->anonymize("target:\n  db: {name: db, user: r, password: r}\n".self::RULES, [], ['--tables' => 'account']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--tables has no effect on anonymize', $tester->getDisplay());
    }

    /**
     * With a `path`, `db.name` may be a variable mapping (TYPO3 `.env`) rather than
     * the database that is about to change, so the prompt does not quote it.
     */
    #[Test]
    public function theConfirmationOnlyNamesALiteralDatabase(): void
    {
        $tester = $this->anonymize("target:\n  path: /var/www/.env\n  db: {name: TYPO3_DB_NAME}\n".self::RULES, ['no'], ['--yes' => false]);

        self::assertStringContainsString('This irreversibly anonymizes the local database. Continue?', $tester->getDisplay());
        self::assertStringNotContainsString('TYPO3_DB_NAME', $tester->getDisplay());
    }

    /**
     * psql ignores the MySQL-style ssl_* keys, so a sync refuses them rather than
     * connect unencrypted. Anonymizing must refuse the same way.
     */
    #[Test]
    public function unsupportedConnectionSettingsAreRefused(): void
    {
        $tester = $this->anonymize("target:\n  db: {type: pgsql, name: db, user: r, password: r, ssl_ca: /etc/ca.pem}\n".self::RULES);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('does not support', $tester->getDisplay());
    }

    /**
     * @param list<string>               $inputs
     * @param array<string, bool|string> $options
     */
    private function anonymize(string $yaml, array $inputs = [], array $options = []): CommandTester
    {
        $file = $this->dir.'/anonymize.yaml';
        file_put_contents($file, $yaml);

        $tester = new CommandTester((new Application())->find('anonymize'));
        $tester->setInputs($inputs);
        $tester->execute(['--config-file' => $file, '--yes' => true, ...$options], ['interactive' => true]);

        return $tester;
    }
}
