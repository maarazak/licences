<?php

namespace App\Services;

use CinetPay\Auth\TokenStore;
use CinetPay\Response\AccessToken;
use Illuminate\Support\Facades\Cache;

class CinetPayTokenStore implements TokenStore
{
    public function get(string $accountKey): ?AccessToken
    {
        $cached = Cache::get("cinetpay:token:{$accountKey}");
        if (is_array($cached) && !empty($cached['token'])) {
            $token = new AccessToken(
                token: $cached['token'],
                type: $cached['type'] ?? 'bearer',
                expiresIn: (int) ($cached['expiresIn'] ?? 86400),
                expiresAt: (int) ($cached['expiresAt'] ?? (time() + 86400)),
            );

            if (!$token->isExpired()) {
                return $token;
            }
        }

        return null;
    }

    public function put(string $accountKey, AccessToken $token): void
    {
        $ttl = max(60, $token->expiresAt - time());
        Cache::put("cinetpay:token:{$accountKey}", [
            'token' => $token->token,
            'type' => $token->type,
            'expiresIn' => $token->expiresIn,
            'expiresAt' => $token->expiresAt,
        ], $ttl);
    }

    public function forget(string $accountKey): void
    {
        Cache::forget("cinetpay:token:{$accountKey}");
    }
}
