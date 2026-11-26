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

namespace KonradMichalik\SyncTool\Tests\Unit\Remote;

use KonradMichalik\SyncTool\Remote\SshControlDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function file_put_contents;
use function fileowner;
use function fileperms;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function unlink;

/**
 * SshControlDirectoryTest.
 *
 * The sockets of a shared SSH connection grant whoever can reach them a login,
 * so the directory they live in must belong to the current user alone.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class SshControlDirectoryTest extends TestCase
{
    private string $base;

    /** The user running the tests, which owns whatever the test creates. */
    private int $uid;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/sync-tool-ctl-'.bin2hex(random_bytes(4));
        mkdir($this->base);
        $this->uid = (int) fileowner($this->base);
    }

    protected function tearDown(): void
    {
        @rmdir($this->base.'/php-sync-tool-ssh-'.$this->uid);
        @unlink($this->base.'/php-sync-tool-ssh-'.$this->uid);
        @unlink($this->base.'/elsewhere');
        @unlink($this->base.'/stray');
        rmdir($this->base);
    }

    #[Test]
    public function createsADirectoryOnlyTheOwnerCanEnter(): void
    {
        $path = SshControlDirectory::ensure($this->base);

        self::assertSame($this->base.'/php-sync-tool-ssh-'.$this->uid, $path);
        self::assertTrue(is_dir((string) $path));
        self::assertSame(0o700, fileperms((string) $path) & 0o777);
    }

    #[Test]
    public function refusesADirectoryOthersCanReach(): void
    {
        $path = $this->base.'/php-sync-tool-ssh-'.$this->uid;
        mkdir($path);
        chmod($path, 0o755);

        self::assertNull(SshControlDirectory::ensure($this->base));
    }

    /**
     * Another user could pre-create the path as a link to a directory of theirs.
     */
    #[Test]
    public function refusesASymbolicLinkInsteadOfTheDirectory(): void
    {
        $target = $this->base.'/elsewhere';
        mkdir($target, 0o700);
        symlink($target, $this->base.'/php-sync-tool-ssh-'.$this->uid);

        self::assertNull(SshControlDirectory::ensure($this->base));

        rmdir($target);
    }

    #[Test]
    public function givesUpWhenTheDirectoryCannotBeCreated(): void
    {
        file_put_contents($this->base.'/stray', '');

        self::assertNull(SshControlDirectory::ensure($this->base.'/stray'));
    }
}
