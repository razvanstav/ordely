<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class ShopifyApi
{
    public function __construct(private Sql $db,private ?ShopifyGateway $gateway=null) {}

    public function handle(string $route,Request $request): Response
    {
        try{$config=AppConfig::fromEnvironment();}catch(\RuntimeException|\InvalidArgumentException){throw new Problem(503,'shopify_not_configured');}
        if($route==='shopify_home'){
            $shop=new ShopDomain($request->query->getString('shop'));$config->allow($shop);
            $html=file_get_contents(dirname(__DIR__,3).'/resources/shopify.html');
            if($html===false){throw new \RuntimeException('Asset unavailable.');}
            return new Response(str_replace('{{CLIENT_ID}}',htmlspecialchars($config->clientId,ENT_QUOTES,'UTF-8'),$html),200,[
                'Content-Type'=>'text/html; charset=UTF-8',
                'Content-Security-Policy'=>"default-src 'self'; script-src 'self' https://cdn.shopify.com; style-src 'self'; connect-src 'self' https://monorail-edge.shopifysvc.com; frame-ancestors https://admin.shopify.com ".$shop->origin()."; base-uri 'none'; form-action 'self'",
            ]);
        }
        $cipher=new SecretCipher(KeyRing::fromEnvironment());
        $installations=new Installations($this->db,$config,$cipher,$this->gateway??new HttpShopifyGateway($config));
        try{
            if($route==='shopify_webhook'){
                (new WebhookInbox($this->db,$config,$cipher,$installations))->receive($request);
                return new JsonResponse(['status'=>'accepted']);
            }
            if(in_array($route,['shopify_connect','shopify_status'],true)){
                $authorization=$request->headers->get('Authorization')??'';
                if(!str_starts_with($authorization,'Bearer ')){throw new InvalidIdToken();}
                $token=substr($authorization,7);$identity=(new IdTokenVerifier($config))->verify($token);
                if($route==='shopify_status'){return new JsonResponse($installations->status($identity));}
                (new SessionGuard($this->db))->validateWrite($request);
                $installations->connect($token,IdentityApi::field(IdentityApi::body($request),'code',64));
                return new JsonResponse(['shop'=>$identity->shop->value,'connected'=>true]);
            }
            $actor=(new SessionGuard($this->db))->context($request);$installations->manage($actor);
            if($route==='shopify_events'){
                return new JsonResponse(['events'=>$this->db->run('SELECT LOWER(HEX(id)) id,topic,status,received_at FROM shopify_webhook_events WHERE merchant_id=? ORDER BY received_at DESC LIMIT 100',[Id::bytes($actor->merchantId)])->fetchAll()]);
            }
            $body=IdentityApi::body($request);$store=IdentityApi::field($body,'storeId',32);$installations->manage($actor,$store);
            if($route==='shopify_intent'){return new JsonResponse($installations->intent($actor,$store,new ShopDomain(IdentityApi::field($body,'shop',253))),201);}
            $refresh=$body['refresh']??false;if(!is_bool($refresh)){throw new Problem(400,'invalid_input');}
            $context=new ConnectionContext(new MerchantId($actor->merchantId),new StoreId($store),new ConnectionId(IdentityApi::field($body,'connectionId',32)),CorrelationId::new());
            $installations->check($context,$refresh);
            return new JsonResponse(['status'=>'connected']);
        }catch(InvalidIdToken){return new JsonResponse(['error'=>'invalid_shopify_identity'],401,['X-Shopify-Retry-Invalid-Session-Request'=>'1']);}
        catch(AuthorizationLost){throw new Problem(409,'shopify_reauthorization_required');}
        catch(ShopifyUnavailable){throw new Problem(503,'shopify_unavailable');}
    }
}
