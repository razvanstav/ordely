<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Presentation;
use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Invoicing\Infrastructure\{InvoiceProfiles,OrderPreparations,OrderDrafts,PreparationCipher,IssueCipher,IssueIntents};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class IssueIntentApi
{
    public function __construct(private Sql $db) {}
    public function handle(Request $request,?string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);$cipher=new SecretCipher(KeyRing::fromEnvironment());
        $source=new OrderPreparations($this->db,new OrderCipher($cipher),new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher));
        $service=new IssueIntents($this->db,new OrderDrafts($this->db,$source,new PreparationCipher($cipher)),new IssueCipher($cipher));
        if($request->isMethod('GET')){return new JsonResponse(['intent'=>$service->get($actor,$request->query->getString('storeId'),$id??'')]);}
        $body=IdentityApi::body($request);
        if($id!==null){
            if(array_diff(array_keys($body),['storeId','expectedVersion'])!==[]||!is_int($body['expectedVersion']??null)){throw new Problem(400,'invalid_input');}
            return new JsonResponse(['intent'=>$service->cancel($actor,IdentityApi::field($body,'storeId',32),$id,$body['expectedVersion'])]);
        }
        if(array_diff(array_keys($body),['storeId','orderId','expectedVersion'])!==[]||!is_int($body['expectedVersion']??null)){throw new Problem(400,'invalid_input');}
        return new JsonResponse(['intent'=>$service->prepare($actor,IdentityApi::field($body,'storeId',32),IdentityApi::field($body,'orderId',32),$body['expectedVersion'])]);
    }
}
