<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Presentation;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Invoicing\Infrastructure\{InvoiceProfiles,OrderPreparations,OrderDrafts,PreparationCipher};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class OrderDraftApi
{
    public function __construct(private Sql $db) {}
    public function handle(string $route,Request $request,?string $order): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);$cipher=new SecretCipher(KeyRing::fromEnvironment());
        $source=new OrderPreparations($this->db,new OrderCipher($cipher),new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher));
        $drafts=new OrderDrafts($this->db,$source,new PreparationCipher($cipher));
        if($request->isMethod('GET')){
            $store=$request->query->getString('storeId');
            return new JsonResponse($route==='order_draft_list'?$drafts->list($actor,$store,$request->query->has('after')?$request->query->getString('after'):null):['draft'=>$drafts->get($actor,$store,$order??'')]);
        }
        $body=IdentityApi::body($request);
        if(array_diff(array_keys($body),['storeId','expectedVersion','orderVersion','profileVersion'])!==[]){throw new Problem(400,'invalid_input');}
        foreach(['expectedVersion','orderVersion','profileVersion'] as $name){if(!is_int($body[$name]??null)){throw new Problem(400,'invalid_version');}}
        $version=$drafts->save($actor,IdentityApi::field($body,'storeId',32),$order??'',$body['expectedVersion'],$body['orderVersion'],$body['profileVersion']);
        return new JsonResponse(['version'=>$version]);
    }
}
