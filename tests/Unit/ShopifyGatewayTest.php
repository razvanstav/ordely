<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Shopify\{HttpShopifyGateway,ShopDomain,TokenSet,ShopifyUnavailable,InvalidIdToken,AuthorizationLost};
use Ordely\Tests\Support\ShopifyFixtures as F;
use PHPUnit\Framework\TestCase;

final class ShopifyGatewayTest extends TestCase
{
    public function testExchangeUsesExpiringOfflineTokensAndStrictShopOrigin(): void
    {
        $before=time();$gateway=new HttpShopifyGateway(F::config(),static function(string $url,array $headers,string $body):array{
            self::assertSame('https://ordely-test.myshopify.com/admin/oauth/access_token',$url);self::assertSame('application/x-www-form-urlencoded',$headers['Content-Type']);
            parse_str($body,$data);self::assertSame('1',$data['expiring']);self::assertSame('urn:shopify:params:oauth:token-type:offline-access-token',$data['requested_token_type']);self::assertSame(F::config()->secret(),$data['client_secret']);
            return ['status'=>200,'body'=>json_encode(['access_token'=>'test-access','refresh_token'=>'test-refresh','expires_in'=>3600,'refresh_token_expires_in'=>86400,'scope'=>''],JSON_THROW_ON_ERROR)];
        });
        $tokens=$gateway->exchange(new ShopDomain('ordely-test.myshopify.com'),F::idToken());
        self::assertGreaterThanOrEqual($before+3600,$tokens->expiresAt);self::assertSame('test-refresh',TokenSet::fromStored($tokens->stored())->secrets->reveal()['refreshToken']);
        self::assertStringNotContainsString('test-access',json_encode($tokens,JSON_THROW_ON_ERROR));
    }
    public function testRefreshAndProbeUseCorrectEndpointsAndHeaders(): void
    {
        $calls=0;$gateway=new HttpShopifyGateway(F::config(),static function(string $url,array $headers,string $body)use(&$calls):array{
            ++$calls;
            if($calls===1){parse_str($body,$data);self::assertSame('refresh_token',$data['grant_type']);self::assertSame('old-refresh',$data['refresh_token']);return ['status'=>200,'body'=>'{"access_token":"new-access","refresh_token":"new-refresh","expires_in":3600,"refresh_token_expires_in":86400,"scope":""}'];}
            self::assertStringEndsWith('/admin/api/2026-07/graphql.json',$url);self::assertSame('new-access',$headers['X-Shopify-Access-Token']);
            return ['status'=>200,'body'=>'{"data":{"shop":{"id":"gid://shopify/Shop/123456","myshopifyDomain":"ordely-test.myshopify.com"}}}'];
        });
        $shop=new ShopDomain('ordely-test.myshopify.com');$tokens=$gateway->refresh($shop,'old-refresh');self::assertSame('123456',$gateway->probe($shop,$tokens->secrets->reveal()['accessToken']));self::assertSame(2,$calls);
    }
    public function testUnsafeResponsesAndStatusesAreSanitized(): void
    {
        $shop=new ShopDomain('ordely-test.myshopify.com');
        foreach([['status'=>302,'body'=>'secret'],['status'=>429,'body'=>'secret'],['status'=>200,'body'=>'not-json'],['status'=>200,'body'=>'{"access_token":"secret"}']] as $response){
            try{(new HttpShopifyGateway(F::config(),static fn()=> $response))->refresh($shop,'refresh');self::fail('Invalid response accepted.');}catch(ShopifyUnavailable $error){self::assertStringNotContainsString('secret',$error->getMessage());}
        }
        try{(new HttpShopifyGateway(F::config(),static fn()=>['status'=>401,'body'=>'private']))->probe($shop,'token');self::fail();}catch(AuthorizationLost $error){self::assertStringNotContainsString("private",$error->getMessage());}
        $this->expectException(InvalidIdToken::class);(new HttpShopifyGateway(F::config(),static fn()=>['status'=>400,'body'=>'private']))->exchange($shop,F::idToken());
    }
    public function testIdTokenCannotAuthorizeRequestToAnotherShop(): void
    {
        $this->expectException(InvalidIdToken::class);(new HttpShopifyGateway(F::config(),static function():never{self::fail('Network should not be reached.');}))->exchange(new ShopDomain('another.myshopify.com'),F::idToken());
    }
}
