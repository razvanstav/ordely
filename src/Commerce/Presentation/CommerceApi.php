<?php
declare(strict_types=1);
namespace Ordely\Commerce\Presentation;

use Ordely\Commerce\Infrastructure\{CommerceFactory,CommerceQuery};
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Presentation\{SessionGuard,IdentityApi};
use Ordely\Infrastructure\Database\Sql;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};

final readonly class CommerceApi
{
    public function __construct(private Sql $db) {}
    public function handle(string $route,Request $request,?string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);
        if ($route==='commerce_privacy') {
            $action=IdentityApi::field(IdentityApi::body($request),'action',32);
            $result=(new \Ordely\Adapters\Shopify\PrivacyRequests($this->db,new \Ordely\Integrations\Infrastructure\SecretCipher(\Ordely\Integrations\Infrastructure\KeyRing::fromEnvironment())))->process($actor,(string)$id,$action);
            return new JsonResponse($result,200,$action==='export'?['Content-Disposition'=>'attachment; filename="ordely-privacy-'.$id.'.json"']:[]);
        }
        if ($route==='commerce_start') {
            $body=IdentityApi::body($request);$store=IdentityApi::field($body,'storeId',32);$connection=IdentityApi::field($body,'connectionId',32);
            $full=$body['full']??false;$restart=$body['restart']??false;
            if (!is_bool($full) || !is_bool($restart)) { throw new \InvalidArgumentException('Invalid import mode.'); }
            $context=new ConnectionContext(new MerchantId($actor->merchantId),new StoreId($store),new ConnectionId($connection),CorrelationId::new());
            return new JsonResponse(['runId'=>CommerceFactory::importer($this->db)->start($actor,$context,$full,$restart)],202);
        }
        $store=$request->query->getString('storeId');$query=new CommerceQuery($this->db,CommerceFactory::cipher());
        if ($route==='commerce_order') { return new JsonResponse(['order'=>$query->order($actor,$store,(string)$id)]); }
        return new JsonResponse($query->list($actor,$store,$request->query->getString('kind','order'),$request->query->has('after')?$request->query->getString('after'):null));
    }
}
