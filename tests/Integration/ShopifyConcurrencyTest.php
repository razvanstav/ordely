<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Adapters\Shopify\{Installations,ShopDomain};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,ProcessHarness,IntegrationFixtures,ShopifyFixtures as F,FakeShopifyGateway};

final class ShopifyConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testConcurrentImportStartsAndWorkersPublishOnce(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $service=new Installations($this->db,F::config(),IntegrationFixtures::cipher(),new FakeShopifyGateway());
        $code=$service->intent($actor,$store,new ShopDomain('ordely-test.myshopify.com'))['code'];$id=$service->connect(F::idToken(),$code);
        $command=['commerce-start',$actor->merchantId,$store,$id,$actor->membershipId,$actor->userId];$runs=[];
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);$runs[]=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR)['id'];}
        self::assertSame($runs[0],$runs[1]);$work=['commerce-work',$actor->merchantId,$store,$id];
        foreach(ProcessHarness::together([$work,$work]) as $result){self::assertSame(0,$result['exit'],$result['error']);self::assertNotContains('dead',json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR));}
        self::assertSame(4,(int)$this->db->run('SELECT COUNT(*) FROM commerce_records WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM outbox_events WHERE merchant_id=? AND event_type='ORDER_IMPORTED'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame('completed',$this->db->run('SELECT status FROM commerce_sync_runs WHERE id=?',[Id::bytes($runs[0])])->fetchColumn());
    }
    public function testConcurrentWorkersRefreshExpiredTokenExactlyOnce(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $gateway=new FakeShopifyGateway();$gateway->expired=true;$service=new Installations($this->db,F::config(),IntegrationFixtures::cipher(),$gateway);
        $code=$service->intent($actor,$store,new ShopDomain('ordely-test.myshopify.com'))['code'];$id=$service->connect(F::idToken(),$code);
        $command=['shopify-refresh',$actor->merchantId,$store,$id];$refreshes=0;
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);$refreshes+=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR)['refreshes'];}
        self::assertSame(1,$refreshes);self::assertSame(2,(int)$this->db->run('SELECT version FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn());
    }
    public function testKeyInventoryRetainsWebhookReferencesAfterConnectionRotation(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$this->db->run("UPDATE stores SET platform_key='shopify' WHERE id=?",[Id::bytes($store)]);
        $old='old-'.Id::new();$keys=[$old=>random_bytes(32),'next'=>random_bytes(32)];$cipher=new \Ordely\Integrations\Infrastructure\SecretCipher(new \Ordely\Integrations\Infrastructure\KeyRing($old,$keys));
        $service=new Installations($this->db,F::config(),$cipher,new FakeShopifyGateway());$code=$service->intent($actor,$store,new ShopDomain('ordely-test.myshopify.com'))['code'];$id=$service->connect(F::idToken(),$code);
        $body='{"shop_id":123456,"shop_domain":"ordely-test.myshopify.com"}';
        $request=\Symfony\Component\HttpFoundation\Request::create('/webhooks/shopify','POST',server:['HTTP_X_SHOPIFY_HMAC_SHA256'=>base64_encode(hash_hmac('sha256',$body,F::config()->secret(),true)),'HTTP_X_SHOPIFY_SHOP_DOMAIN'=>'ordely-test.myshopify.com','HTTP_X_SHOPIFY_TOPIC'=>'customers/data_request','HTTP_X_SHOPIFY_WEBHOOK_ID'=>'key-inventory-test'],content:$body);
        (new \Ordely\Adapters\Shopify\WebhookInbox($this->db,F::config(),$cipher,$service))->receive($request);
        $next=new \Ordely\Integrations\Infrastructure\SecretCipher(new \Ordely\Integrations\Infrastructure\KeyRing('next',$keys));
        (new \Ordely\Integrations\Infrastructure\Connections($this->db,IntegrationFixtures::registry(),$next))->rotate($actor,$id,1);
        $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/key-status.php','--test'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));
        if(!is_resource($process)){self::fail('Key inventory process unavailable.');}
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error===false?'':$error);
        $data=json_decode($output===false?'':$output,true,flags:JSON_THROW_ON_ERROR);self::assertNotContains($old,array_column($data['keyUsage'],'key_id'));self::assertContains($old,array_column($data['webhookKeyUsage'],'key_id'));
    }
}
