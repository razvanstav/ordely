<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

/** Identity is not authorization to a merchant, store or operation in Ordely. */
final readonly class ShopIdentity
{
    public function __construct(public ShopDomain $shop, public string $userId, public int $issuedAt) {}
}
