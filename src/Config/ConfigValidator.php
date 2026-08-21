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

/**
 * ConfigValidator.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class ConfigValidator
{
    /**
     * Lifecycle commands. `script` is the documented spelling, `scripts` the one
     * the code has always read, so both are accepted.
     */
    private const SCRIPT_SCHEMA = '{
        "type": "object",
        "additionalProperties": false,
        "properties": {
            "before": {"type": "string"},
            "after": {"type": "string"},
            "error": {"type": "string"}
        }
    }';

    /**
     * Every key an endpoint block may carry. `additionalProperties` is false, so a
     * misspelled key is reported instead of silently doing nothing: `ignore_tabel`
     * used to pass validation and then simply not ignore anything. Keys starting
     * with `x` or `.` are left to the author, which keeps YAML anchor blocks
     * (`.defaults: &defaults`) usable.
     */
    private const CLIENT_SCHEMA = '{
        "type": "object",
        "additionalProperties": false,
        "patternProperties": {"^[x.]": {}},
        "properties": {
            "name": {"type": "string"},
            "host": {"type": "string"},
            "user": {"type": "string"},
            "password": {"type": "string"},
            "path": {"type": "string"},
            "ssh_key": {"type": "string"},
            "port": {"type": "number"},
            "dump_dir": {"type": "string"},
            "keep_dumps": {"type": "number"},
            "after_dump": {"type": "string"},
            "link": {"type": "string"},
            "protect": {"type": "boolean"},
            "post_sql": {"type": "array"},
            "console": {"type": "object"},
            "jump_host": {
                "type": "object",
                "additionalProperties": false,
                "properties": {
                    "name": {"type": "string"},
                    "host": {"type": "string"},
                    "user": {"type": "string"},
                    "password": {"type": "string"},
                    "ssh_key": {"type": "string"},
                    "private": {"type": "string"},
                    "port": {"type": "number"}
                }
            },
            "db": {
                "type": "object",
                "additionalProperties": false,
                "properties": {
                    "name": {"type": "string"},
                    "host": {"type": "string"},
                    "user": {"type": "string"},
                    "password": {"type": "string"},
                    "port": {"type": "number"},
                    "ssl_disabled": {"type": "boolean"},
                    "ssl_skip_verify": {"type": "boolean"},
                    "ssl_ca": {"type": "string"},
                    "ssl_capath": {"type": "string"},
                    "ssl_cert": {"type": "string"},
                    "ssl_key": {"type": "string"},
                    "ssl_cipher": {"type": "string"},
                    "type": {"enum": ["mysql", "MySQL", "mariadb", "MariaDB", "postgres", "postgresql", "PostgreSQL", "pgsql"]}
                }
            },
            "script": %SCRIPT%,
            "scripts": %SCRIPT%,
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
     * The root block. Everything `SyncConfig::fromArray()` reads is listed here,
     * including the legacy singular spellings, so that `additionalProperties`
     * being false rejects typos rather than supported configuration.
     */
    private const ROOT_SCHEMA = '{
        "type": "object",
        "additionalProperties": false,
        "patternProperties": {"^[x.]": {}},
        "properties": {
            "type": {"enum": ["TYPO3", "Symfony", "Drupal", "WordPress", "Laravel"]},
            "verbose": {"type": "boolean"},
            "mute": {"type": "boolean"},
            "dry_run": {"type": "boolean"},
            "yes": {"type": "boolean"},
            "reverse": {"type": "boolean"},
            "keep_dump": {"type": "boolean"},
            "dump_name": {"type": "string"},
            "check_dump": {"type": "boolean"},
            "clear_database": {"type": "boolean"},
            "import": {"type": "string"},
            "tables": {"type": "string"},
            "where": {"type": "string"},
            "additional_mysqldump_options": {"type": "string"},
            "ignore_table": {"type": "array"},
            "ignore_tables": {"type": "array"},
            "truncate_table": {"type": "array"},
            "truncate_tables": {"type": "array"},
            "use_rsync": {"type": "boolean"},
            "use_rsync_options": {"type": "string"},
            "use_sshpass": {"type": "boolean"},
            "files": {"type": ["array", "object"]},
            "files_options": {"type": "string"},
            "with_files": {"type": "boolean"},
            "files_only": {"type": "boolean"},
            "ssh_agent": {"type": "boolean"},
            "force_password": {"type": "boolean"},
            "ssh_strict_host_key_checking": {"type": "boolean"},
            "ssh_password": {
                "type": "object",
                "additionalProperties": false,
                "properties": {
                    "origin": {"type": "string"},
                    "target": {"type": "string"}
                }
            },
            "config_file_path": {"type": "string"},
            "log_file": {"type": "string"},
            "json_log": {"type": "boolean"},
            "script": %SCRIPT%,
            "scripts": %SCRIPT%,
            "target": %CLIENT%,
            "origin": %CLIENT%,
            "local": %CLIENT%
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
        return str_replace(
            ['%CLIENT%', '%SCRIPT%'],
            [str_replace('%SCRIPT%', self::SCRIPT_SCHEMA, self::CLIENT_SCHEMA), self::SCRIPT_SCHEMA],
            self::ROOT_SCHEMA,
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
