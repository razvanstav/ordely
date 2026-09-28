<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Adapters\Shopify\{ShopifyGateway,ShopDomain,TokenSet};
use Ordely\Integrations\Domain\Secrets;
final class FakeShopifyGateway implements ShopifyGateway
{
    public int $exchanges=0;
    public int $refreshes=0;
    public int $probes=0;
    public ?\Throwable $failure=null;
    public bool $expired=false;
    public function exchange(ShopDomain $shop,string $idToken): TokenSet {++$this->exchanges;return $this->tokens();}
    public function refresh(ShopDomain $shop,string $refreshToken): TokenSet {++$this->refreshes;$this->expired=false;return $this->tokens();}
    public function probe(ShopDomain $shop,string $accessToken): string {++$this->probes;if($this->failure!==null){throw $this->failure;}return '123456';}
    private function tokens(): TokenSet {return new TokenSet(new Secrets(['accessToken'=>'SYNTHETIC-ACCESS-'.$this->refreshes,'refreshToken'=>'SYNTHETIC-REFRESH-'.$this->refreshes,'scope'=>'""']),time()+($this->expired?-5:3600),time()+86400);}
}
