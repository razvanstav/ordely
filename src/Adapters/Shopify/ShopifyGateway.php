<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;
interface ShopifyGateway
{
    public function exchange(ShopDomain $shop,#[\SensitiveParameter] string $idToken): TokenSet;
    public function refresh(ShopDomain $shop,#[\SensitiveParameter] string $refreshToken): TokenSet;
    public function probe(ShopDomain $shop,#[\SensitiveParameter] string $accessToken): string;
}
