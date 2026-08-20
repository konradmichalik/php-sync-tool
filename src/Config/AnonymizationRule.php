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

use KonradMichalik\SyncTool\Enum\AnonymizationStrategy;
use KonradMichalik\SyncTool\Exception\ConfigException;

use function is_array;
use function is_string;
use function sprintf;

/**
 * AnonymizationRule.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class AnonymizationRule
{
    public function __construct(
        public string $table,
        public string $column,
        public AnonymizationStrategy $strategy,
        public ?string $value = null,
    ) {}

    /**
     * Parses the `anonymize` block: a map of table to column to strategy, where a
     * strategy is either its name or an object carrying a `value` with it.
     *
     * @param array<string, mixed>|null $data
     *
     * @return list<self>
     *
     * @throws ConfigException on an unknown strategy or a missing required value
     */
    public static function fromConfig(?array $data): array
    {
        if (null === $data) {
            return [];
        }

        $rules = [];

        foreach ($data as $table => $columns) {
            if (!is_array($columns)) {
                throw new ConfigException(sprintf('Anonymization for "%s" must be a map of columns', $table));
            }

            foreach ($columns as $column => $spec) {
                $rules[] = self::fromSpec((string) $table, (string) $column, $spec);
            }
        }

        return $rules;
    }

    private static function fromSpec(string $table, string $column, mixed $spec): self
    {
        $target = sprintf('%s.%s', $table, $column);

        [$name, $value] = match (true) {
            is_string($spec) => [$spec, null],
            is_array($spec) => [
                is_string($spec['strategy'] ?? null) ? $spec['strategy'] : '',
                is_string($spec['value'] ?? null) ? $spec['value'] : null,
            ],
            // An unquoted `null` in YAML arrives here and reads as "no strategy".
            default => ['', null],
        };

        $strategy = AnonymizationStrategy::fromConfigValue($name);

        if (null === $strategy) {
            throw new ConfigException(sprintf('Unknown anonymization strategy "%s" for %s. Use null, static, hash or email (quote "null" in YAML).', $name, $target));
        }

        if ($strategy->requiresValue() && null === $value) {
            throw new ConfigException(sprintf('Anonymization strategy "%s" for %s needs a value', $strategy->value, $target));
        }

        return new self($table, $column, $strategy, $value);
    }
}
