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

use KonradMichalik\SyncTool\Remote\{HostKeyStatus, KnownHosts};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * KnownHostsTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
final class KnownHostsTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/known_hosts_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Test]
    public function missingFileIsUnknown(): void
    {
        self::assertSame(
            HostKeyStatus::Unknown,
            (new KnownHosts('/no/such/known_hosts'))->match('example.com', 22, 'ssh-ed25519 AAAAKEY'),
        );
    }

    #[Test]
    public function plainHostMatch(): void
    {
        $kh = $this->write("example.com ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Matched, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    #[Test]
    public function mismatchOnSameTypeDifferentKey(): void
    {
        $kh = $this->write("example.com ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Mismatch, $kh->match('example.com', 22, 'ssh-ed25519 DIFFERENT'));
    }

    #[Test]
    public function unknownWhenHostAbsent(): void
    {
        $kh = $this->write("other.com ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    #[Test]
    public function nonDefaultPortUsesBracketNotation(): void
    {
        $kh = $this->write("[example.com]:2222 ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Matched, $kh->match('example.com', 2222, 'ssh-ed25519 AAAAKEY'));
        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    #[Test]
    public function rsaSignatureFormatNormalizesToSshRsa(): void
    {
        // known_hosts stores the key type ssh-rsa; the server may present rsa-sha2-512.
        $kh = $this->write("example.com ssh-rsa AAAARSAKEY\n");
        self::assertSame(HostKeyStatus::Matched, $kh->match('example.com', 22, 'rsa-sha2-512 AAAARSAKEY'));
        self::assertSame(HostKeyStatus::Mismatch, $kh->match('example.com', 22, 'rsa-sha2-256 OTHERRSA'));
    }

    #[Test]
    public function hashedHostMatch(): void
    {
        $salt = random_bytes(20);
        $hash = base64_encode(hash_hmac('sha1', 'example.com', $salt, true));
        $line = '|1|'.base64_encode($salt).'|'.$hash.' ssh-ed25519 AAAAKEY';
        $kh = $this->write($line."\n");

        self::assertSame(HostKeyStatus::Matched, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
        self::assertSame(HostKeyStatus::Mismatch, $kh->match('example.com', 22, 'ssh-ed25519 DIFFERENT'));
    }

    #[Test]
    public function malformedServerKeyIsUnknown(): void
    {
        $kh = $this->write("example.com ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'onlyonefield'));
    }

    #[Test]
    public function malformedHashedEntriesAreSkipped(): void
    {
        // A |1| entry with the wrong number of segments and one with an invalid
        // base64 salt must both be skipped without matching.
        $kh = $this->write("|1|tooFewParts ssh-ed25519 AAAAKEY\n|1|!!!notbase64!!!|zzz ssh-ed25519 AAAAKEY\n");
        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    #[Test]
    public function hashedEntryForDifferentHostDoesNotMatch(): void
    {
        $salt = random_bytes(20);
        $hash = base64_encode(hash_hmac('sha1', 'other.example.com', $salt, true));
        $line = '|1|'.base64_encode($salt).'|'.$hash.' ssh-ed25519 AAAAKEY';
        $kh = $this->write($line."\n");

        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    #[Test]
    public function commentsAndMarkersAreSkipped(): void
    {
        $kh = $this->write("# a comment\n@revoked example.com ssh-ed25519 REVOKEDKEY\n");
        self::assertSame(HostKeyStatus::Unknown, $kh->match('example.com', 22, 'ssh-ed25519 AAAAKEY'));
    }

    private function write(string $contents): KnownHosts
    {
        file_put_contents($this->file, $contents);

        return new KnownHosts($this->file);
    }
}
