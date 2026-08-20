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

namespace KonradMichalik\SyncTool\Recipe;

use KonradMichalik\SyncTool\Config\{ClientConfig, DatabaseConfig, SyncConfig};
use KonradMichalik\SyncTool\Enum\Framework;
use KonradMichalik\SyncTool\Remote\CommandRunner;

use function basename;
use function escapeshellarg;
use function is_array;
use function json_decode;
use function sprintf;

/**
 * CredentialResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class CredentialResolver
{
    public function __construct(
        private FrameworkDetector $detector = new FrameworkDetector(),
        private CredentialValidator $validator = new CredentialValidator(),
    ) {}

    public function resolve(SyncConfig $config, ClientConfig $client, CommandRunner $runner): ?DatabaseConfig
    {
        $framework = null !== $config->type && '' !== $config->type
            ? Framework::fromString($config->type)
            : $this->detector->detect($config);

        if (null === $framework) {
            return null;
        }

        $file = basename($client->path);
        $phpBin = $client->console['php'] ?? 'php';
        $strategy = $this->readStrategy($framework, $file);

        $content = $runner->run($this->readCommand($strategy, $phpBin, $client->path), ReadStrategy::DrupalDrush === $strategy);

        if ('' === $content && Framework::Drupal === $framework && ReadStrategy::PlainFile === $strategy) {
            $content = $runner->run($this->readCommand(ReadStrategy::DrupalDrush, $phpBin, $client->path));
            $creds = self::extract($framework, '__drush__', $content);
        } else {
            $creds = self::extract($framework, $file, $content);
        }

        $clientLabel = '' !== $client->host ? $client->host : 'local';
        $this->validator->validate($clientLabel, $creds);

        return new DatabaseConfig(
            name: (string) ($creds['name'] ?? ''),
            host: (string) ($creds['host'] ?? ''),
            user: (string) ($creds['user'] ?? ''),
            password: (string) ($creds['password'] ?? ''),
            port: (int) ($creds['port'] ?? 0),
        );
    }

    public function readStrategy(Framework $framework, string $file): ReadStrategy
    {
        if (Framework::Typo3 === $framework
            && ('LocalConfiguration.php' === $file || 'settings.php' === $file)
        ) {
            return ReadStrategy::Typo3PhpInclude;
        }

        if (Framework::Drupal === $framework && 'settings.php' === $file) {
            return ReadStrategy::PlainFile;
        }

        return ReadStrategy::PlainFile;
    }

    public function readCommand(ReadStrategy $strategy, string $phpBin, string $path): string
    {
        return match ($strategy) {
            ReadStrategy::PlainFile => sprintf('cat %s', escapeshellarg($path)),
            ReadStrategy::Typo3PhpInclude => sprintf(
                '%s -r %s',
                $phpBin,
                escapeshellarg(sprintf("echo json_encode(include '%s');", $path)),
            ),
            ReadStrategy::DrupalDrush => 'drush core-status --pipe --fields=db-hostname,db-username,db-password,db-name,db-port --format=json',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function extract(Framework $framework, string $file, string $content): array
    {
        return match ($framework) {
            Framework::Typo3 => self::extractTypo3($file, $content),
            Framework::Symfony => self::extractSymfony($file, $content),
            Framework::Drupal => self::extractDrupal($file, $content),
            Framework::WordPress => Extractors::wordpressFromConfig($content),
            Framework::Laravel => Extractors::laravelFromEnv($content),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractTypo3(string $file, string $content): array
    {
        if ('.env' === $file) {
            return Extractors::typo3FromEnv($content);
        }

        if ('AdditionalConfiguration.php' === $file || 'additional.php' === $file) {
            return Extractors::typo3FromAdditional($content);
        }

        // LocalConfiguration.php or settings.php — content is json_encode output
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return ['name' => '', 'host' => '', 'user' => '', 'password' => '', 'port' => ''];
        }

        $db = $decoded['DB'] ?? $decoded;

        return Parsing::parseTypo3DatabaseCredentials($db);
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractSymfony(string $file, string $content): array
    {
        if ('parameters.yml' === $file) {
            return Extractors::symfonyFromParameters($content);
        }

        $db = Parsing::parseSymfonyDatabaseUrl(Extractors::symfonyDatabaseUrlLine($content));

        return [
            'name' => $db->name,
            'host' => $db->host,
            'user' => $db->user,
            'password' => $db->password,
            'port' => (string) $db->port,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractDrupal(string $file, string $content): array
    {
        if ('__drush__' === $file) {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return ['name' => '', 'host' => '', 'user' => '', 'password' => '', 'port' => ''];
            }

            return Parsing::parseDrupalDrushCredentials($decoded);
        }

        return Extractors::drupalFromSettings($content);
    }
}
