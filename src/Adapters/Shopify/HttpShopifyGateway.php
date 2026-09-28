<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

final readonly class HttpShopifyGateway implements ShopifyGateway
{
    /** @param (\Closure(string,array<string,string>,string):array{status:int,body:string})|null $transport */
    public function __construct(private AppConfig $config,private ?\Closure $transport=null) {}

    public function exchange(ShopDomain $shop,#[\SensitiveParameter] string $idToken): TokenSet
    {
        $identity=(new IdTokenVerifier($this->config))->verify($idToken);
        if($identity->shop->value!==$shop->value){throw new InvalidIdToken();}
        return $this->token($shop,[
            'grant_type'=>'urn:ietf:params:oauth:grant-type:token-exchange','subject_token'=>$idToken,
            'subject_token_type'=>'urn:ietf:params:oauth:token-type:id_token',
            'requested_token_type'=>'urn:shopify:params:oauth:token-type:offline-access-token','expiring'=>'1',
        ],true);
    }

    public function refresh(ShopDomain $shop,#[\SensitiveParameter] string $refreshToken): TokenSet
    {
        return $this->token($shop,['grant_type'=>'refresh_token','refresh_token'=>$refreshToken],false);
    }

    /** @param array<string,string> $parameters */
    private function token(ShopDomain $shop,#[\SensitiveParameter] array $parameters,bool $exchange): TokenSet
    {
        $requestedAt=time();
        $response=$this->request($shop,'/admin/oauth/access_token',['Content-Type'=>'application/x-www-form-urlencoded'],http_build_query([
            'client_id'=>$this->config->clientId,'client_secret'=>$this->config->secret(),...$parameters,
        ],'', '&',PHP_QUERY_RFC3986));
        if($response['status']===400&&$exchange){throw new InvalidIdToken();}
        if(in_array($response['status'],[400,401,403],true)){throw new AuthorizationLost();}
        if($response['status']!==200){throw new ShopifyUnavailable();}
        return TokenSet::fromResponse($this->json($response['body']),$requestedAt);
    }

    public function probe(ShopDomain $shop,#[\SensitiveParameter] string $accessToken): string
    {
        $response=$this->request($shop,'/admin/api/'.AppConfig::API_VERSION.'/graphql.json',[
            'Content-Type'=>'application/json','X-Shopify-Access-Token'=>$accessToken,
        ],json_encode(['query'=>'query OrdelyConnectionCheck { shop { id myshopifyDomain } }'],JSON_THROW_ON_ERROR));
        if(in_array($response['status'],[401,403],true)){throw new AuthorizationLost();}
        if($response['status']!==200){throw new ShopifyUnavailable();}
        $data=$this->json($response['body']);
        if(isset($data['errors'])||($data['data']['shop']['myshopifyDomain']??null)!==$shop->value){throw new ShopifyUnavailable();}
        $id=$data['data']['shop']['id']??null;
        if(!is_string($id)||!preg_match('~^gid://shopify/Shop/([1-9][0-9]{0,24})$~D',$id,$matches)){throw new ShopifyUnavailable();}
        return $matches[1];
    }

    /** @return array<string,mixed> */
    private function json(#[\SensitiveParameter] string $body): array
    {
        try{$data=json_decode($body,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ShopifyUnavailable();}
        if(!is_array($data)){throw new ShopifyUnavailable();}return $data;
    }

    /** @param array<string,string> $headers
     * @return array{status:int,body:string} */
    private function request(ShopDomain $shop,string $path,#[\SensitiveParameter] array $headers,#[\SensitiveParameter] string $body): array
    {
        $this->config->allow($shop);$url=$shop->origin().$path;
        if($this->transport!==null){return ($this->transport)($url,$headers,$body);}
        $curl=curl_init($url);if($curl===false){throw new ShopifyUnavailable();}
        $received='';$lines=[];foreach($headers as $name=>$value){$lines[]=$name.': '.$value;}
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$lines,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>static function(\CurlHandle $handle,string $chunk)use(&$received):int{
                if(strlen($received)+strlen($chunk)>65536){return 0;}$received.=$chunk;return strlen($chunk);
            },
        ]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        // Transport ambiguity is safe to retry for this auth boundary, never for a business mutation.
        if($ok===false){throw new ShopifyUnavailable();}return ['status'=>$status,'body'=>$received];
    }
}
