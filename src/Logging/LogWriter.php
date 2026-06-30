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

namespace KonradMichalik\SyncTool\Logging;

use Closure;

/**
 * LogWriter.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final readonly class LogWriter
{
    /** @var Closure(): string */
    private Closure $clock;

    /**
     * @param Closure(string): void  $console
     * @param Closure(): string|null $clock
     */
    public function __construct(
        private bool $json,
        private ?string $logFile,
        private Closure $console,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): string => date(\DATE_ATOM);
    }

    public function log(string $message): void
    {
        $line = $this->format($message);
        ($this->console)($line);

        if (null !== $this->logFile) {
            file_put_contents($this->logFile, $line."\n", \FILE_APPEND);
        }
    }

    private function format(string $message): string
    {
        if (!$this->json) {
            return $message;
        }

        return json_encode(
            ['time' => ($this->clock)(), 'message' => $message],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );
    }
}
