<?php

namespace Tests\Unit;

use App\Import\LegacyEncrypt;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LegacyEncryptTest extends TestCase
{
    private const KEY = 'a3f1c2d4e5b60718293a4b5c6d7e8f90112233445566778899aabbccddeeff00';

    public function test_round_trip_matches_the_tkwiki_format(): void
    {
        $crypt = new LegacyEncrypt(self::KEY);
        $stored = $crypt->encrypt('hunter2');

        // mac(32) . base64(nonce16 . ciphertext), all base64-encoded
        $raw = base64_decode($stored, true);
        $this->assertSame(16 + 7, strlen(base64_decode(substr($raw, 32), true)));

        $this->assertSame('hunter2', $crypt->decrypt($stored));
        $this->assertSame('', $crypt->decrypt(''));
    }

    public function test_decrypts_a_value_made_by_tk_encrypt(): void
    {
        // Built with tkwiki's Tk\Encrypt::safeEncrypt('Ready, aim', KEY).
        $this->assertSame('Ready, aim', (new LegacyEncrypt(self::KEY))->decrypt(
            'ORDgFuo6HJGaJEE4CGEdxjHVp51xfMJGAMWJD8TAYIlEdnNORzhUcm1LQlJuVmNQSzhYSERpTHhxYS9SQjRsYUljWT0='));
    }

    public function test_wrong_key_fails(): void
    {
        $stored = (new LegacyEncrypt(self::KEY))->encrypt('hunter2');

        $this->expectException(RuntimeException::class);
        (new LegacyEncrypt(str_repeat('0', 64)))->decrypt($stored);
    }

    public function test_key_must_be_hex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LegacyEncrypt('not-a-key');
    }
}
