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

namespace MoveElevator\DbSyncTool\Config;

use JsonException;
use MoveElevator\DbSyncTool\Exception\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function is_array;
use function sprintf;

/**
 * ConfigLoader.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     *
     * @throws ConfigException
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new ConfigException(sprintf('Configuration file not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new ConfigException(sprintf('Configuration file could not be read: %s', $path));
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $data = match ($extension) {
            'json' => $this->parseJson($contents, $path),
            'yaml', 'yml' => $this->parseYaml($contents, $path),
            default => $this->parseAuto($contents, $path),
        };

        if (!is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new ConfigException(sprintf('Configuration file does not contain a mapping: %s', $path));
        }

        /* @var array<string, mixed> $data */
        return $data;
    }

    private function parseJson(string $contents, string $path): mixed
    {
        try {
            return json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigException(sprintf('Invalid JSON in %s: %s', $path, $e->getMessage()), 0, $e);
        }
    }

    private function parseYaml(string $contents, string $path): mixed
    {
        try {
            return Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new ConfigException(sprintf('Invalid YAML in %s: %s', $path, $e->getMessage()), 0, $e);
        }
    }

    private function parseAuto(string $contents, string $path): mixed
    {
        try {
            return json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->parseYaml($contents, $path);
        }
    }
}
