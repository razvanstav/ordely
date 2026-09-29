<?php
declare(strict_types=1);
namespace Ordely\Adapters\Oblio;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};

final class OblioTransport
{
    /** @param array<string,string> $headers
     * @return array{status:int,body:string,retryAfter:?int} */
    public static function send(string $method, string $path, #[\SensitiveParameter] array $headers, #[\SensitiveParameter] ?string $body): array
    {
        // Only authentication and the three read endpoints are reachable in this step.
        if(!(($method==='POST'&&$path==='/authorize/token')||($method==='GET'&&preg_match('#^/nomenclature/(companies|series|vat_rates)(\?cif=[A-Za-z0-9%._~-]+)?$#D',$path)))){throw new \LogicException('Unsupported Oblio endpoint.');}
        $handle=curl_init('https://www.oblio.eu/api'.$path);
        $response='';$retryAfter=null;
        curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>array_map(static fn(string $name,string $value):string=>$name.': '.$value,array_keys($headers),array_values($headers)),
            CURLOPT_WRITEFUNCTION=>static function(\CurlHandle $curl,string $chunk)use(&$response):int {if(strlen($response)+strlen($chunk)>1048576){return 0;}$response.=$chunk;return strlen($chunk);},
            CURLOPT_HEADERFUNCTION=>static function(\CurlHandle $curl,string $line)use(&$retryAfter):int {if(preg_match('/^Retry-After:\s*(\d{1,5})\s*$/i',$line,$match)){$retryAfter=min(86400,(int)$match[1]);}return strlen($line);},
        ]);
        if($body!==null){curl_setopt($handle,CURLOPT_POSTFIELDS,$body);}
        try{if(curl_exec($handle)===false){throw new ProviderFailure(ErrorCategory::Transient);}return ['status'=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body'=>$response,'retryAfter'=>$retryAfter];}
        finally{curl_close($handle);}
    }
}
