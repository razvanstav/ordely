<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

/** An outbound Shopify host, never an arbitrary URL or a merchant's custom domain. */
final readonly class ShopDomain
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.myshopify\.com$/D', $value)) {
            throw new \InvalidArgumentException('Invalid Shopify domain.');
        }
    }

    public function origin(): string { return 'https://' . $this->value; }
}
