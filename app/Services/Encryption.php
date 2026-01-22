<?php

namespace App\Services;

class Encryption
{
    private const CIPHER = 'aes-256-cbc';
    private const PREFIX = 'enc::';

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        $key = self::key();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $plain; // fail open to avoid data loss
        }
        return self::PREFIX . base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // PHP 7/8 compatible prefix check (avoid str_starts_with on PHP<8)
        if (substr($value, 0, strlen(self::PREFIX)) === self::PREFIX) {
            $payload = substr($value, strlen(self::PREFIX));
            $decoded = base64_decode($payload, true);
            if ($decoded === false) {
                return $value;
            }
            $ivLength = openssl_cipher_iv_length(self::CIPHER);
            $iv = substr($decoded, 0, $ivLength);
            $cipher = substr($decoded, $ivLength);
            $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
            return $plain === false ? $value : $plain;
        }
        return $value; // legacy plain text
    }

    private static function key(): string
    {
        $key = env('ENCRYPTION_KEY');
        if (!$key) {
            throw new \RuntimeException('ENCRYPTION_KEY missing');
        }
        return hash('sha256', $key, true);
    }
}
