<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Oblio\{OblioProvider,OblioTransport};
use Ordely\Core\Contracts\{Capability,ErrorCategory,ProviderFailure};
use Ordely\Core\Value as V;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Tests\Support\{ContractFixtures as C,OblioFixtures as F};
use PHPUnit\Framework\TestCase;

final class OblioProviderTest extends TestCase
{
    public function testAuthenticatesAndReadsOnlyVerifiedCompanyWithoutChangingTaxPrecision(): void
    {
        $calls=[];$adapter=new OblioProvider(F::credentials(),static function(string $method,string $path,array $headers,?string $body)use(&$calls):array {
            $calls[]=[$method,$path];
            if($path==='/authorize/token'){self::assertSame('POST',$method);self::assertSame('client_id=oblio%40example.test&client_secret=SYNTHETIC-oblio-secret',$body);self::assertArrayNotHasKey('Authorization',$headers);}
            else{self::assertSame('GET',$method);self::assertNull($body);self::assertSame('Bearer synthetic-access-token',$headers['Authorization']);}
            return F::reply($path);
        });
        $data=$adapter->readConfiguration(C::context(),new V\ExternalId('TEST001'));
        self::assertSame([['id'=>'TEST001','name'=>'Synthetic company']],$data->companies);self::assertSame('TEST001',$data->companyId);
        self::assertSame([['name'=>'TEST','default'=>true]],$data->series);self::assertSame('7.1250',$data->taxRates[0]['percent']);self::assertSame('Synthetic fractional 7.1250',$data->taxRates[0]['name']);self::assertSame('0',$data->taxRates[1]['percent']);
        self::assertSame([['POST','/authorize/token'],['GET','/nomenclature/companies'],['GET','/nomenclature/series?cif=TEST001'],['GET','/nomenclature/vat_rates?cif=TEST001']],$calls);
        self::assertSame([Capability::InvoiceConfiguration],$adapter->capabilities(C::context())->features);
    }
    public function testUnknownCompanyStopsBeforeQueryingItsSeries(): void
    {
        $paths=[];$adapter=new OblioProvider(F::credentials(),static function(string $method,string $path)use(&$paths):array {$paths[]=$path;return F::reply($path);});
        try{$adapter->readConfiguration(C::context(),new V\ExternalId('FOREIGN'));self::fail('Expected validation.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Validation,$failure->category);}
        self::assertSame(['/authorize/token','/nomenclature/companies'],$paths);
    }
    public function testAuthenticationAndRateLimitFailuresNeverExposeResponseBodies(): void
    {
        foreach([[401,ErrorCategory::Authentication,null],[403,ErrorCategory::Authentication,null],[429,ErrorCategory::Transient,17],[500,ErrorCategory::Transient,null],[302,ErrorCategory::Transient,null]] as [$status,$category,$delay]){
            $adapter=new OblioProvider(F::credentials(),static fn():array=>['status'=>$status,'body'=>'PRIVATE upstream diagnostic','retryAfter'=>$delay]);
            try{$adapter->readConfiguration(C::context());self::fail('Expected failure.');}catch(ProviderFailure $failure){self::assertSame($category,$failure->category);self::assertSame($delay,$failure->retryAfterSeconds);self::assertStringNotContainsString('PRIVATE',$failure->getMessage());}
        }
    }
    public function testMalformedTokenAndCatalogueDataAreRejected(): void
    {
        $cases=[['/authorize/token','{"access_token":"bad\\r\\nheader","token_type":"Bearer"}'],['/authorize/token','[]'],['/authorize/token','not-json'],['/nomenclature/companies','{"status":200,"data":{}}'],['/nomenclature/companies','{"status":200,"data":[{"cif":"TEST001"}]}'],['/nomenclature/companies','{"status":200,"data":[{"cif":"TEST001","company":"A"},{"cif":"TEST001","company":"B"}]}'],['/nomenclature/vat_rates','{"status":200,"data":[{"name":"Invalid","percent":101,"default":true}]}'],['/nomenclature/vat_rates','{"status":200,"data":[{"name":"Invalid","percent":7.12345,"default":true}]}'],['/nomenclature/series','{"status":200,"data":[{"type":"Factura","name":"TEST","default":"true"}]}']];
        foreach($cases as [$endpoint,$body]){
            $adapter=new OblioProvider(F::credentials(),static fn(string $method,string $path):array=>explode('?',$path)[0]===$endpoint?['status'=>200,'body'=>$body,'retryAfter'=>null]:F::reply($path));
            try{$adapter->readConfiguration(C::context(),new V\ExternalId('TEST001'));self::fail('Expected malformed reply rejection.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Transient,$failure->category);}
        }
    }
    public function testInvalidCredentialsCannotBePersistedThroughRegistry(): void
    {
        $registry=require dirname(__DIR__,2).'/config/providers.php';
        foreach([['clientId'=>'not-email','clientSecret'=>'synthetic'],['clientId'=>'test@example.test','clientSecret'=>"header\r\ninjection"],['apiToken'=>'wrong-shape']] as $values){
            try{$registry->get('oblio')->validate(new Secrets($values));self::fail('Expected credential rejection.');}catch(\InvalidArgumentException){self::addToAssertionCount(1);}
        }
    }
    public function testEveryDocumentOperationFailsWithoutAnyNetworkCall(): void
    {
        $adapter=new OblioProvider(F::credentials(),static function():never {self::fail('No document transport is authorized.');});$ctx=C::context();$id=new V\ExternalId('TEST');$key=new V\OperationKey('read-only');
        foreach([fn()=>$adapter->createInvoice($ctx,C::invoice(),$key),fn()=>$adapter->cancelInvoice($ctx,$id,'Test',$key),fn()=>$adapter->createCreditNote($ctx,$id,C::invoice(),'Test',$key),fn()=>$adapter->getInvoice($ctx,$id),fn()=>$adapter->getPdf($ctx,$id),fn()=>$adapter->sendInvoice($ctx,$id,new V\EmailAddress('test@example.test'),$key)] as $operation){
            try{$operation();self::fail('Expected unsupported operation.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Unsupported,$failure->category);}
        }
        $this->expectException(\LogicException::class);OblioTransport::send('POST','/docs/invoice',[],null);
    }
}
