<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

final readonly class IdTokenVerifier
{
    public function __construct(private AppConfig $config) {}

    public function verify(#[\SensitiveParameter] string $token, ?int $now = null): ShopIdentity
    {
        try {
            if (strlen($token) > 8192) { throw new InvalidIdToken(); }
            $parts = explode('.', $token);
            if (count($parts) !== 3) { throw new InvalidIdToken(); }
            [$header, $payload, $signature] = $parts;
            $decodedHeader = json_decode($this->decode($header), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($decodedHeader) || ($decodedHeader['alg'] ?? null) !== 'HS256' || isset($decodedHeader['crit'])) {
                throw new InvalidIdToken();
            }
            if (!hash_equals(hash_hmac('sha256', $header . '.' . $payload, $this->config->secret(), true), $this->decode($signature))) {
                throw new InvalidIdToken();
            }
            $claims = json_decode($this->decode($payload), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($claims)) { throw new InvalidIdToken(); }
            foreach (['exp', 'nbf', 'iat'] as $field) {
                if (!is_int($claims[$field] ?? null)) { throw new InvalidIdToken(); }
            }
            $now ??= time();
            if ($claims['exp'] <= $now || $claims['nbf'] > $now || $claims['iat'] > $now
                || $claims['exp'] <= $claims['iat'] || $claims['nbf'] > $claims['exp']
                || ($claims['aud'] ?? null) !== $this->config->clientId) {
                throw new InvalidIdToken();
            }
            $destination = $claims['dest'] ?? null;
            if (!is_string($destination) || !str_starts_with($destination, 'https://')) { throw new InvalidIdToken(); }
            $shop = new ShopDomain(substr($destination, 8));
            if (($claims['iss'] ?? null) !== $shop->origin() . '/admin'
                || !is_string($claims['sub'] ?? null) || !preg_match('/^[1-9][0-9]{0,24}$/D', $claims['sub'])) {
                throw new InvalidIdToken();
            }
            $this->config->allow($shop);
            return new ShopIdentity($shop, $claims['sub'], $claims['iat']);
        } catch (\Throwable) {
            // Never retain the rejected bearer token or a decoder's diagnostic text.
            throw new InvalidIdToken();
        }
    }

    private function decode(string $part): string
    {
        if ($part === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $part)) { throw new InvalidIdToken(); }
        $decoded = base64_decode(strtr($part, '-_', '+/'), true);
        if ($decoded === false || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $part) {
            throw new InvalidIdToken();
        }
        return $decoded;
    }
}
