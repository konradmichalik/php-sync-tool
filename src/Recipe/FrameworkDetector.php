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

use KonradMichalik\SyncTool\Config\SyncConfig;
use KonradMichalik\SyncTool\Enum\Framework;

use function in_array;

/**
 * FrameworkDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class FrameworkDetector
{
    /**
     * @var array<string, list<string>>
     */
    private const MAPPING = [
        Framework::Typo3->value => ['LocalConfiguration.php', 'AdditionalConfiguration.php', 'additional.php'],
        Framework::Symfony->value => ['.env', 'parameters.yml'],
        Framework::Drupal->value => ['settings.php'],
        Framework::WordPress->value => ['wp-config.php'],
        Framework::Laravel->value => ['.env'],
    ];

    public function detect(SyncConfig $config): ?Framework
    {
        if (null !== $config->type && '' !== $config->type) {
            return null;
        }

        if ('' !== $config->origin->db->name || '' !== $config->target->db->name) {
            return null;
        }

        $detected = null;

        foreach ([$config->origin, $config->target] as $client) {
            if ('' === $client->path) {
                continue;
            }

            $path = $client->path;
            $file = basename($path);

            if ('settings.php' === $file
                && (str_contains($path, '/config/system/') || str_contains($path, '/typo3conf/system/'))) {
                return Framework::Typo3;
            }

            foreach (self::MAPPING as $framework => $files) {
                if (in_array($file, $files, true)) {
                    $detected = Framework::from($framework);
                }
            }
        }

        return $detected;
    }
}
