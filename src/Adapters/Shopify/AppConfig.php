<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

use Ordely\Infrastructure\Configuration\Environment;

final readonly class AppConfig
{
    public const API_VERSION = '2026-07';

    public function __construct(
        public string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        public ?ShopDomain $developmentShop = null,
    ) {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,128}$/D', $clientId) || strlen($clientSecret) < 16 || strlen($clientSecret) > 512) {
            throw new \InvalidArgumentException('Shopify configuration unavailable.');
        }
    }

    public static function fromEnvironment(): self
    {
        $dev = Environment::string('APP_ENV', 'dev') !== 'prod';
        $shop = Environment::string('ORDELY_SHOPIFY_DEV_STORE', '');
        if ($dev && $shop === '') { throw new \RuntimeException('A development shop must be configured.'); }
        return new self(
            Environment::string('ORDELY_SHOPIFY_CLIENT_ID', Environment::string('SHOPIFY_API_KEY', '')),
            Environment::string('ORDELY_SHOPIFY_CLIENT_SECRET', Environment::string('SHOPIFY_API_SECRET', '')),
            $dev ? new ShopDomain($shop) : null,
        );
    }

    public function allow(ShopDomain $shop): void
    {
        if ($this->developmentShop !== null && $this->developmentShop->value !== $shop->value) {
            throw new \InvalidArgumentException('Shop is outside the development environment.');
        }
    }

    /** Only the signature and OAuth boundaries may access this value. */
    public function secret(): string { return $this->clientSecret; }

    /** @return array<string,string> */
    public function __debugInfo(): array { return ['clientId' => $this->clientId, 'clientSecret' => '[REDACTED]']; }

    /** @return never */
    public function __serialize(): array { throw new \LogicException('App secrets cannot be serialized.'); }
}
