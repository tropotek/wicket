<?php

namespace App\Import;

use InvalidArgumentException;
use RuntimeException;

/**
 * tkwiki's Tk\Encrypt (encrypt-then-MAC, aes-256-ctr), ported byte for byte. Its safeEncrypt()
 * passes the derived encryption key where unsafeEncrypt() expects a "base64 encode" flag, so a
 * stored value is base64(mac . base64(nonce . ciphertext)) and AES uses the master key.
 */
class LegacyEncrypt
{
    private const METHOD = 'aes-256-ctr';

    private const HASH = 'sha256';

    private string $key;

    /** @param  string  $hexKey  tkwiki's `system.encrypt` config value */
    public function __construct(string $hexKey)
    {
        $key = preg_match('/^(?:[0-9a-f]{2})+$/i', trim($hexKey)) ? hex2bin(trim($hexKey)) : false;
        if ($key === false) {
            throw new InvalidArgumentException('The old key must be tkwiki\'s system.encrypt value (a hex string).');
        }
        $this->key = $key;
    }

    /** @throws RuntimeException when the value wasn't encrypted with this key */
    public function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        $raw = base64_decode($stored, true);
        $macSize = strlen(hash(self::HASH, '', true));
        if ($raw === false || strlen($raw) <= $macSize) {
            throw new RuntimeException('Not a tkwiki encrypted value.');
        }

        $inner = substr($raw, $macSize);
        if (! hash_equals(substr($raw, 0, $macSize), hash_hmac(self::HASH, $inner, $this->authKey(), true))) {
            throw new RuntimeException('The old key does not match.');
        }

        $payload = base64_decode($inner, true);
        $nonceSize = openssl_cipher_iv_length(self::METHOD) ?: 16;
        $plain = $payload === false ? false
            : openssl_decrypt(substr($payload, $nonceSize), self::METHOD, $this->key, OPENSSL_RAW_DATA, substr($payload, 0, $nonceSize));

        if ($plain === false) {
            throw new RuntimeException('Not a tkwiki encrypted value.');
        }

        return $plain;
    }

    /** The tkwiki format, for test fixtures. */
    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(openssl_cipher_iv_length(self::METHOD) ?: 16);
        $inner = base64_encode($nonce.openssl_encrypt($plain, self::METHOD, $this->key, OPENSSL_RAW_DATA, $nonce));

        return base64_encode(hash_hmac(self::HASH, $inner, $this->authKey(), true).$inner);
    }

    private function authKey(): string
    {
        return hash_hmac(self::HASH, 'AUTHENTICATION', $this->key, true);
    }
}
