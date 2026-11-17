<?php

namespace App\Services;

class CryptoService
{
    protected static function getKey(): string
    {
        $raw = env('ENCRYPTION_KEY', '');
        return base64_decode($raw);
    }

    protected static function base64urlDecode(string $data): string
    {
        $b64 = strtr($data, '-_', '+/');
        return base64_decode($b64);
    }

    protected static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decrypt(?string $payload, string $aad = ''): ?string
    {
        if (!$payload) return null;

        $parts = explode('.', $payload);
        if (count($parts) < 4) return $payload;

        [$version, $ivB64, $tagB64, $dataB64] = $parts;

        $key = self::getKey();
        $iv = self::base64urlDecode($ivB64);
        $tag = self::base64urlDecode($tagB64);
        $data = self::base64urlDecode($dataB64);

        $decrypted = openssl_decrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $version === 'v2' ? $aad : ''
        );

        return $decrypted !== false ? $decrypted : null;
    }

    public static function encrypt(string $plain, string $aad = ''): string
    {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        return implode('.', [
            'v2',
            self::base64urlEncode($iv),
            self::base64urlEncode($tag),
            self::base64urlEncode($encrypted),
        ]);
    }
}
