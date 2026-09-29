<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Presentation;
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId,ExternalId};
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Invoicing\Application\ReadInvoiceConfiguration;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class InvoiceConfigurationApi
{
    public function __construct(private Sql $db,private ?ProviderRegistry $registry=null) {}
    public function handle(Request $request): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);$body=IdentityApi::body($request);
        $version=$body['version']??null;if(!is_int($version)||$version<1){throw new Problem(400,'invalid_version');}
        $context=new ConnectionContext(new MerchantId($actor->merchantId),new StoreId(IdentityApi::field($body,'storeId',32)),new ConnectionId(IdentityApi::field($body,'connectionId',32)),CorrelationId::new());
        $company=isset($body['companyId'])?new ExternalId(IdentityApi::field($body,'companyId',255)):null;
        /** @var ProviderRegistry $registry */
        $registry=$this->registry??require dirname(__DIR__,3).'/config/providers.php';
        try{$configuration=(new ReadInvoiceConfiguration($this->db,$registry,new SecretCipher(KeyRing::fromEnvironment())))->read($actor,$context,$version,$company);}
        catch(ProviderFailure $failure){
            $status=match($failure->category){ErrorCategory::Authentication=>424,ErrorCategory::Validation=>422,ErrorCategory::Unsupported=>422,default=>503};
            return new JsonResponse(['error'=>'invoice_provider_'.$failure->category->value],$status,$failure->retryAfterSeconds===null?[]:['Retry-After'=>(string)$failure->retryAfterSeconds]);
        }
        return new JsonResponse($configuration);
    }
}
