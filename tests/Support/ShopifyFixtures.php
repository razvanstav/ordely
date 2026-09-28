<?php

declare(strict_types=1);

namespace Ordely\Tests\Support;

use Ordely\Adapters\Shopify\{AppConfig,ShopDomain};

final class ShopifyFixtures
{
    public static function config(): AppConfig
    {
        return new AppConfig('synthetic-client-id', 'synthetic-shopify-secret-for-tests', new ShopDomain('ordely-test.myshopify.com'));
    }

    /** @param array<string,mixed> $overrides
     * @param array<string,mixed> $header */
    public static function idToken(array $overrides = [], array $header = ['alg' => 'HS256', 'typ' => 'JWT'], ?string $secret = null): string
    {
        $now = time();
        $claims = array_replace([
            'iss' => 'https://ordely-test.myshopify.com/admin', 'dest' => 'https://ordely-test.myshopify.com',
            'aud' => self::config()->clientId, 'sub' => '42', 'exp' => $now + 60, 'iat' => $now, 'nbf' => $now,
            'jti' => 'test-nonce', 'sid' => 'test-session',
        ], $overrides);
        $encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $encode(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . $encode(json_encode($claims, JSON_THROW_ON_ERROR));
        return $unsigned . '.' . $encode(hash_hmac('sha256', $unsigned, $secret ?? self::config()->secret(), true));
    }
}
