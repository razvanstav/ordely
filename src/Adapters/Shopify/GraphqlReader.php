<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};

/** Only repository-owned read operations are accepted. Never logs provider responses. */
final readonly class GraphqlReader
{
    /** @param (\Closure(string,array<string,string>,string):array{status:int,body:string,retryAfter?:int})|null $transport */
    public function __construct(private Installations $installations, private AppConfig $config, private ?\Closure $transport = null) {}

    /** @param array<string,string|null> $variables
     * @return array<string,mixed> */
    public function read(ConnectionContext $context, string $operation, array $variables): array
    {
        if (!in_array($operation, ['orders','order','products','variants','inventory'], true)) { throw new \InvalidArgumentException('Unknown import operation.'); }
        $query = file_get_contents(dirname(__DIR__,3).'/resources/shopify/'.$operation.'.graphql');
        if ($query === false) { throw new \LogicException('Missing query.'); }
        try {
            return $this->installations->withAccess($context, function(ShopDomain $shop, #[\SensitiveParameter] string $token) use ($query,$variables): array {
                $this->config->allow($shop);
                $headers = ['Content-Type'=>'application/json','X-Shopify-Access-Token'=>$token];
                $body = json_encode(['query'=>$query,'variables'=>$variables], JSON_THROW_ON_ERROR);
                $url = $shop->origin().'/admin/api/'.AppConfig::API_VERSION.'/graphql.json';
                $response = $this->transport !== null ? ($this->transport)($url,$headers,$body) : $this->send($url,$headers,$body);
                if ($response['status'] === 401) { throw new AuthorizationLost(); }
                if ($response['status'] === 403) { throw new ProviderFailure(ErrorCategory::Authentication); }
                if ($response['status'] === 429 || $response['status'] >= 500) { throw new ProviderFailure(ErrorCategory::Transient, min(86400,max(1,$response['retryAfter']??5))); }
                if ($response['status'] !== 200) { throw new ProviderFailure(ErrorCategory::Validation); }
                try { $data = json_decode($response['body'],true,64,JSON_THROW_ON_ERROR); }
                catch (\JsonException) { throw new ProviderFailure(ErrorCategory::Transient); }
                if (!is_array($data)) { throw new ProviderFailure(ErrorCategory::Transient); }
                if (!empty($data['errors'])) {
                    $codes = array_column(array_column($data['errors'],'extensions'),'code');
                    if (in_array('THROTTLED',$codes,true)) { throw new ProviderFailure(ErrorCategory::Transient,5); }
                    throw new ProviderFailure(in_array('ACCESS_DENIED',$codes,true)?ErrorCategory::Authentication:ErrorCategory::Validation);
                }
                if (!is_array($data['data']??null)) { throw new ProviderFailure(ErrorCategory::Transient); }
                return $data['data'];
            });
        } catch (AuthorizationLost) { throw new ProviderFailure(ErrorCategory::Authentication); }
        catch (ShopifyUnavailable) { throw new ProviderFailure(ErrorCategory::Transient); }
    }

    /** @param array<string,string> $headers
     * @return array{status:int,body:string,retryAfter:int} */
    private function send(string $url, #[\SensitiveParameter] array $headers, string $body): array
    {
        $curl = curl_init($url); if ($curl === false) { throw new ProviderFailure(ErrorCategory::Transient); }
        $received = ''; $retry = 5; $lines = [];
        foreach ($headers as $key=>$value) { $lines[]=$key.': '.$value; }
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$lines,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HEADERFUNCTION=>static function(\CurlHandle $handle,string $line) use (&$retry):int {
                if (preg_match('/^Retry-After:\s*(\d+)/i',$line,$match)) { $retry=min(86400,(int)$match[1]); } return strlen($line);
            },
            CURLOPT_WRITEFUNCTION=>static function(\CurlHandle $handle,string $chunk) use (&$received):int {
                if (strlen($received)+strlen($chunk)>2097152) { return 0; } $received.=$chunk;return strlen($chunk);
            },
        ]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if ($ok===false) { throw new ProviderFailure(ErrorCategory::Transient); }
        return ['status'=>$status,'body'=>$received,'retryAfter'=>$retry];
    }
}
