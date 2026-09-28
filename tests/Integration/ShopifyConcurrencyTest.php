<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Adapters\Shopify\{Installations,ShopDomain};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,ProcessHarness,IntegrationFixtures,ShopifyFixtures as F,FakeShopifyGateway};

final class ShopifyConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testConcurrentWorkersRefreshExpiredTokenExactlyOnce(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $gateway=new FakeShopifyGateway();$gateway->expired=true;$service=new Installations($this->db,F::config(),IntegrationFixtures::cipher(),$gateway);
        $code=$service->intent($actor,$store,new ShopDomain('ordely-test.myshopify.com'))['code'];$id=$service->connect(F::idToken(),$code);
        $command=['shopify-refresh',$actor->merchantId,$store,$id];$refreshes=0;
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);$refreshes+=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR)['refreshes'];}
        self::assertSame(1,$refreshes);self::assertSame(2,(int)$this->db->run('SELECT version FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn());
    }
}
