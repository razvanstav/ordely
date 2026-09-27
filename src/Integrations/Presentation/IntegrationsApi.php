<?php
declare(strict_types=1);
namespace Ordely\Integrations\Presentation;
use Ordely\Core\Contracts\{CommerceConnector,CarrierProvider,InvoiceProvider,ConnectionContext};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use Ordely\Integrations\Infrastructure\{Connections,ConnectionResolver,KeyRing,SecretCipher};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class IntegrationsApi
{
    public function __construct(private Sql $db) {}
    public function handle(string $route,Request $request,?string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);
        /** @var ProviderRegistry $registry */
        $registry=require dirname(__DIR__,3).'/config/providers.php';
        $service=new Connections($this->db,$registry);$service->manage($actor);
        if($route==='integrations_list'){return new JsonResponse(['providers'=>$registry->metadata(),'connections'=>$service->list($actor)]);}
        $body=IdentityApi::body($request);
        if(in_array($route,['integrations_create','integrations_rotate','integrations_credentials','integrations_capabilities'],true)){
            $cipher=new SecretCipher(KeyRing::fromEnvironment());$service=new Connections($this->db,$registry,$cipher);
            if($route==='integrations_capabilities'){
                $store=IdentityApi::field($body,'storeId',32);$kind=ProviderKind::tryFrom(IdentityApi::field($body,'kind',16))??throw new Problem(400,'invalid_input');
                $context=new ConnectionContext(new MerchantId($actor->merchantId),new StoreId($store),new ConnectionId($id??''),CorrelationId::new());
                $features=(new ConnectionResolver($this->db,$registry,$cipher))->withProvider($context,$kind,static fn(CommerceConnector|CarrierProvider|InvoiceProvider $provider)=>$provider->capabilities($context));
                return new JsonResponse(['features'=>array_map(static fn($feature):string=>$feature->value,$features->features),'countries'=>$features->countries,'currencies'=>array_map(static fn($currency):string=>$currency->code,$features->currencies)]);
            }
        }
        if($route==='integrations_create'){
            $id=$service->create($actor,IdentityApi::field($body,'id',32),IdentityApi::field($body,'provider',64),IdentityApi::field($body,'label',160),$this->credentials($body));
            return new JsonResponse(['id'=>$id],201);
        }
        $version=$body['version']??null;if(!is_int($version)||$version<1){throw new Problem(400,'invalid_version');}
        switch($route){
            case 'integrations_rotate': $service->rotate($actor,$id??'',$version);break;
            case 'integrations_credentials': $service->rotate($actor,$id??'',$version,$this->credentials($body));break;
            case 'integrations_revoke': $service->revoke($actor,$id??'',$version);break;
            case 'integrations_bind': case 'integrations_unbind':
                $default=$body['isDefault']??false;if(!is_bool($default)){throw new Problem(400,'invalid_input');}
                $service->bind($actor,$id??'',$version,IdentityApi::field($body,'storeId',32),$default,$route==='integrations_unbind');break;
            default:throw new Problem(404,'not_found');
        }
        return new JsonResponse(['status'=>'updated']);
    }
    /** @param array<string,mixed> $body */
    private function credentials(#[\SensitiveParameter] array $body): Secrets
    {
        $raw=$body['credentials']??null;if(!$raw instanceof \stdClass){throw new Problem(400,'invalid_credentials_shape');}
        $values=[];foreach(get_object_vars($raw) as $name=>$value){if(!is_string($value)){throw new Problem(400,'invalid_input');}$values[$name]=$value;}return new Secrets($values);
    }
}
