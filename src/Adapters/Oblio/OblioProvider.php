<?php
declare(strict_types=1);
namespace Ordely\Adapters\Oblio;
use Ordely\Core\Contracts\{Capability,CapabilitySet,ConnectionContext,ErrorCategory,InvoiceConfigurationReader,InvoiceProvider,ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
use Ordely\Integrations\Domain\Secrets;

final readonly class OblioProvider implements InvoiceProvider, InvoiceConfigurationReader
{
    /** @var \Closure(string,string,array<string,string>,?string):array{status:int,body:string,retryAfter:?int} */
    private \Closure $transport;
    /** @param (\Closure(string,string,array<string,string>,?string):array{status:int,body:string,retryAfter:?int})|null $transport */
    public function __construct(#[\SensitiveParameter] private Secrets $credentials, ?\Closure $transport=null)
    {
        self::validateCredentials($credentials);$this->transport=$transport??OblioTransport::send(...);
    }
    public static function validateCredentials(#[\SensitiveParameter] Secrets $credentials): void
    {
        $values=$credentials->reveal();$email=$values['clientId']??'';$secret=$values['clientSecret']??'';
        if(count($values)!==2||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||$secret===''||strlen($secret)>4096||preg_match('/[\x00-\x20\x7f]/',$secret)){throw new \InvalidArgumentException('Invalid Oblio credentials.');}
    }
    public function capabilities(ConnectionContext $context): CapabilitySet { return new CapabilitySet([Capability::InvoiceConfiguration]); }
    public function readConfiguration(ConnectionContext $context, ?V\ExternalId $company=null): D\InvoiceConfiguration
    {
        $credentials=$this->credentials->reveal();
        $auth=$this->request('POST','/authorize/token',['Content-Type'=>'application/x-www-form-urlencoded'],http_build_query(['client_id'=>$credentials['clientId'],'client_secret'=>$credentials['clientSecret']],'','&',PHP_QUERY_RFC3986));
        $token=$auth['access_token']??null;
        if(!is_string($token)||$token===''||strlen($token)>8192||preg_match('/[\x00-\x20\x7f]/',$token)||($auth['token_type']??null)!=='Bearer'){throw new ProviderFailure(ErrorCategory::Transient);}
        $headers=['Authorization'=>'Bearer '.$token];$companies=[];$ids=[];
        foreach($this->rows('/nomenclature/companies',$headers) as $row){
            $id=$this->field($row,'cif');if(isset($ids[$id])){throw new ProviderFailure(ErrorCategory::Transient);}$ids[$id]=true;
            $companies[]=['id'=>$id,'name'=>$this->field($row,'company')];
        }
        if($company===null){return new D\InvoiceConfiguration($companies);}
        if(!isset($ids[$company->value])){throw new ProviderFailure(ErrorCategory::Validation);}
        $query='?cif='.rawurlencode($company->value);$series=[];$names=[];
        foreach($this->rows('/nomenclature/series'.$query,$headers) as $row){
            if($this->field($row,'type')!=='Factura'){continue;}
            $name=$this->field($row,'name');if(isset($names[$name])){throw new ProviderFailure(ErrorCategory::Transient);}$names[$name]=true;
            $series[]=['name'=>$name,'default'=>$this->flag($row)];
        }
        $rates=[];
        foreach($this->rows('/nomenclature/vat_rates'.$query,$headers) as $row){
            $percent=$this->field($row,'percent');
            if(!preg_match('/^(?:100(?:\.0{1,4})?|(?:0|[1-9][0-9]?)(?:\.[0-9]{1,4})?)$/D',$percent)){throw new ProviderFailure(ErrorCategory::Transient);}
            $rates[]=['name'=>$this->field($row,'name'),'percent'=>$percent,'default'=>$this->flag($row)];
        }
        return new D\InvoiceConfiguration($companies,$company->value,$series,$rates);
    }
    /** @param array<string,string> $headers
     * @return list<array<string,mixed>> */
    private function rows(string $path, #[\SensitiveParameter] array $headers): array
    {
        $result=$this->request('GET',$path,$headers);$status=$result['status']??null;
        if($status==='401'||$status==='403'){throw new ProviderFailure(ErrorCategory::Authentication);}
        if($status==='429'){throw new ProviderFailure(ErrorCategory::Transient,10);}
        if($status!=='200'||!is_array($result['data']??null)||!array_is_list($result['data'])||count($result['data'])>1000){throw new ProviderFailure(ErrorCategory::Transient);}
        $rows=[];foreach($result['data'] as $row){if(!$row instanceof \stdClass){throw new ProviderFailure(ErrorCategory::Transient);}$rows[]=get_object_vars($row);}return $rows;
    }
    /** @param array<string,string> $headers
     * @return array<string,mixed> */
    private function request(string $method,string $path,#[\SensitiveParameter] array $headers,#[\SensitiveParameter] ?string $body=null): array
    {
        $result=($this->transport)($method,$path,['Accept'=>'application/json',...$headers],$body);$status=$result['status'];
        if($status===401||$status===403){throw new ProviderFailure(ErrorCategory::Authentication);}
        if($status===429){throw new ProviderFailure(ErrorCategory::Transient,$result['retryAfter']??10);}
        if($status!==200||strlen($result['body'])>1048576){throw new ProviderFailure(ErrorCategory::Transient);}
        try{
            // Validate JSON first, then preserve every numeric lexeme as text (including fractional VAT).
            json_decode($result['body'],false,32,JSON_THROW_ON_ERROR);
            $lossless=preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/',static fn(array $match):string=>'"'.$match[0].'"',$result['body']);
            $decoded=json_decode($lossless??'',false,32,JSON_THROW_ON_ERROR);
        }catch(\JsonException){throw new ProviderFailure(ErrorCategory::Transient);}
        if(!$decoded instanceof \stdClass){throw new ProviderFailure(ErrorCategory::Transient);}return get_object_vars($decoded);
    }
    /** @param array<string,mixed> $row */
    private function field(array $row,string $key): string
    {
        $value=$row[$key]??null;if(!is_string($value)||trim($value)===''||mb_strlen($value)>255||preg_match('/[\x00-\x1f\x7f]/',$value)){throw new ProviderFailure(ErrorCategory::Transient);}return $value;
    }
    /** @param array<string,mixed> $row */
    private function flag(array $row): bool { $value=$row['default']??null;if(!is_bool($value)){throw new ProviderFailure(ErrorCategory::Transient);}return $value; }
    public function createInvoice(ConnectionContext $context,D\InvoiceDraft $draft,V\OperationKey $key): D\InvoiceSnapshot { throw new ProviderFailure(ErrorCategory::Unsupported); }
    public function cancelInvoice(ConnectionContext $context,V\ExternalId $invoice,string $reason,V\OperationKey $key): D\ActionResult { throw new ProviderFailure(ErrorCategory::Unsupported); }
    public function createCreditNote(ConnectionContext $context,V\ExternalId $original,D\InvoiceDraft $credit,string $reason,V\OperationKey $key): D\InvoiceSnapshot { throw new ProviderFailure(ErrorCategory::Unsupported); }
    public function getInvoice(ConnectionContext $context,V\ExternalId $invoice): D\InvoiceSnapshot { throw new ProviderFailure(ErrorCategory::Unsupported); }
    public function getPdf(ConnectionContext $context,V\ExternalId $invoice): D\Document { throw new ProviderFailure(ErrorCategory::Unsupported); }
    public function sendInvoice(ConnectionContext $context,V\ExternalId $invoice,V\EmailAddress $recipient,V\OperationKey $key): D\ActionResult { throw new ProviderFailure(ErrorCategory::Unsupported); }
}
