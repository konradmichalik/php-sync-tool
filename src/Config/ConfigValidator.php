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

use KonradMichalik\SyncTool\Exception\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;

use function implode;
use function is_array;
use function sprintf;

/**
 * ConfigValidator.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigValidator
{
    private const CLIENT_SCHEMA = '{
        "type": "object",
        "properties": {
            "name": {"type": "string"},
            "host": {"type": "string"},
            "user": {"type": "string"},
            "password": {"type": "string"},
            "path": {"type": "string"},
            "ssh_key": {"type": "string"},
            "port": {"type": "number"},
            "dump_dir": {"type": "string"},
            "after_dump": {"type": "string"},
            "link": {"type": "string"},
            "protect": {"type": "boolean"},
            "post_sql": {"type": "array"},
            "console": {"type": "object"},
            "jump_host": {
                "type": "object",
                "properties": {
                    "host": {"type": "string"},
                    "user": {"type": "string"},
                    "password": {"type": "string"},
                    "ssh_key": {"type": "string"},
                    "port": {"type": "number"}
                }
            },
            "db": {
                "type": "object",
                "properties": {
                    "name": {"type": "string"},
                    "host": {"type": "string"},
                    "user": {"type": "string"},
                    "password": {"type": "string"},
                    "port": {"type": "number"},
                    "ssl_disabled": {"type": "boolean"},
                    "type": {"enum": ["mysql", "MySQL", "mariadb", "MariaDB", "postgres", "postgresql", "PostgreSQL", "pgsql"]}
                }
            },
            "script": {
                "type": "object",
                "properties": {
                    "before": {"type": "string"},
                    "after": {"type": "string"},
                    "error": {"type": "string"}
                }
            },
            "anonymize": {
                "type": "object",
                "additionalProperties": {
                    "type": "object",
                    "additionalProperties": {
                        "oneOf": [
                            {"enum": ["null", "static", "hash", "email"]},
                            {
                                "type": "object",
                                "properties": {
                                    "strategy": {"enum": ["null", "static", "hash", "email"]},
                                    "value": {"type": "string"}
                                },
                                "required": ["strategy"]
                            }
                        ]
                    }
                }
            }
        }
    }';

    /**
     * @param array<string, mixed> $config
     *
     * @throws ValidationException collecting all schema errors
     */
    public function validate(array $config): void
    {
        $validator = new Validator();
        $validator->setMaxErrors(100);

        $result = $validator->validate($this->toJsonData($config), $this->schema());

        $error = $result->error();
        if (null === $error) {
            $this->assertAnonymizationTargetsTheTarget($config);

            return;
        }

        $messages = (new ErrorFormatter())->formatFlat($error);

        throw new ValidationException('Configuration validation failed'.([] === $messages ? '' : ': '.implode('; ', $messages)));
    }

    /**
     * Masking rewrites rows in place. On the origin that would rewrite the system
     * being copied from, so the block is target-only.
     *
     * @param array<string, mixed> $config
     *
     * @throws ValidationException
     */
    private function assertAnonymizationTargetsTheTarget(array $config): void
    {
        $origin = $config['origin'] ?? null;

        if (is_array($origin) && [] !== ($origin['anonymize'] ?? [])) {
            throw new ValidationException('Configuration validation failed: "anonymize" is only supported on the target, not on the origin');
        }
    }

    private function schema(): string
    {
        return sprintf(
            '{"type": "object", "properties": {'
            .'"type": {"enum": ["TYPO3", "Symfony", "Drupal", "WordPress", "Laravel"]},'
            .'"log_file": {"type": "string"},'
            .'"ssh_strict_host_key_checking": {"type": "boolean"},'
            .'"json_log": {"type": "boolean"},'
            .'"ignore_table": {"type": "array"},'
            .'"target": %1$s,'
            .'"origin": %1$s'
            .'}}',
            self::CLIENT_SCHEMA,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function toJsonData(array $config): mixed
    {
        if ([] === $config) {
            return new stdClass();
        }

        return json_decode(json_encode($config, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
    }
}
