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

namespace KonradMichalik\SyncTool\Tests\Unit\Output\Progress;

use KonradMichalik\PhpProgress\Terminal\Capabilities;
use KonradMichalik\SyncTool\Output\Progress\LiveProgressFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * LiveProgressFactoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class LiveProgressFactoryTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+');

        if (false === $stream) {
            throw new RuntimeException('Unable to open the test stream.');
        }

        $this->stream = $stream;
    }

    #[Test]
    public function barRendersLabelPercentAndTheFinalMessage(): void
    {
        $bar = $this->factory()->bar('Transferring dump');
        $bar->progress(50.0);
        $bar->succeed('Transfer complete');

        $output = $this->rendered();

        self::assertStringContainsString('Transferring dump', $output);
        self::assertStringContainsString('50%', $output);
        self::assertStringContainsString('[ok] Transfer complete', $output);
    }

    #[Test]
    public function spinnerRendersItsLabelAndTheFailureMessage(): void
    {
        $spinner = $this->factory()->spinner('Importing dump');
        $spinner->fail('Import failed');

        $output = $this->rendered();

        self::assertStringContainsString('Importing dump', $output);
        self::assertStringContainsString('[fail] Import failed', $output);
    }

    private function factory(): LiveProgressFactory
    {
        return new LiveProgressFactory(
            $this->stream,
            new Capabilities(tty: false, width: 80, colors: 'none', unicode: false),
        );
    }

    private function rendered(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }
}
