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

namespace KonradMichalik\SyncTool\Config;

use KonradMichalik\SyncTool\Enum\Framework;
use Symfony\Component\Yaml\Yaml;

use function is_file;
use function sprintf;

/**
 * ProjectScaffold.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class ProjectScaffold
{
    public const DIRECTORY = '.sync-tool';

    /**
     * Where each framework keeps the file that carries database credentials,
     * most specific candidate first.
     *
     * @var array<string, list<string>>
     */
    private const CANDIDATES = [
        Framework::Typo3->value => [
            'config/system/settings.php',
            'public/typo3conf/LocalConfiguration.php',
            'web/typo3conf/LocalConfiguration.php',
            'typo3conf/LocalConfiguration.php',
        ],
        Framework::Drupal->value => [
            'web/sites/default/settings.php',
            'sites/default/settings.php',
        ],
        Framework::WordPress->value => [
            'wp-config.php',
            'public/wp-config.php',
        ],
        Framework::Symfony->value => ['.env'],
        Framework::Laravel->value => ['.env'],
    ];

    /**
     * The framework this directory looks like, by the credential file it carries.
     */
    public function detectFramework(string $projectDir): ?Framework
    {
        foreach (self::CANDIDATES as $framework => $paths) {
            foreach ($paths as $path) {
                if (is_file($projectDir.'/'.$path)) {
                    return Framework::from($framework);
                }
            }
        }

        return null;
    }

    /**
     * The credential file to propose for a framework: the one that exists, or
     * else that framework's most common location.
     */
    public function proposePath(string $projectDir, Framework $framework): string
    {
        $candidates = self::CANDIDATES[$framework->value];

        foreach ($candidates as $path) {
            if (is_file($projectDir.'/'.$path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $local
     */
    public function defaults(Framework $framework, array $local): string
    {
        return $this->render(
            [
                '# Shared settings for this project. `local` describes this machine and is',
                '# used as the other side by `sync-tool pull` and `sync-tool push`.',
            ],
            ['type' => $framework->value, 'local' => $local],
        );
    }

    /**
     * @param array<string, mixed> $origin
     */
    public function environment(string $name, array $origin): string
    {
        return $this->render(
            [
                sprintf('# The "%s" environment.', $name),
                sprintf('#   sync-tool pull %s   pulls its database into this project', $name),
                sprintf('#   sync-tool push %s   sends this project\'s database there', $name),
                '#',
                '# Add `protect: true` to refuse this environment as a sync target.',
            ],
            ['origin' => $origin],
        );
    }

    /**
     * @param list<string>         $comments
     * @param array<string, mixed> $data
     */
    private function render(array $comments, array $data): string
    {
        return implode("\n", $comments)."\n".Yaml::dump($data, 4, 2);
    }
}
