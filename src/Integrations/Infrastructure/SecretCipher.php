<?php
declare(strict_types=1);
namespace Ordely\Integrations\Infrastructure;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Shared\Id;

final readonly class SecretCipher
{
    public function __construct(private KeyRing $ring) {}
    public function encrypt(string $merchant,string $connection,string $provider,Secrets $secrets): string
    {
        $plain=json_encode($secrets->reveal(),JSON_THROW_ON_ERROR);
        if(strlen($plain)>65536){throw new \InvalidArgumentException('Credentials too large.');}
        $keyId=$this->ring->active;$nonce=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$this->ring->key($keyId),OPENSSL_RAW_DATA,$nonce,$tag,$this->aad($merchant,$connection,$provider,$keyId),16);
        if($cipher===false){throw new \RuntimeException('Credential encryption failed.');}
        return json_encode(['v'=>1,'key'=>$keyId,'nonce'=>base64_encode($nonce),'tag'=>base64_encode($tag),'cipher'=>base64_encode($cipher)],JSON_THROW_ON_ERROR);
    }
    public function decrypt(string $merchant,string $connection,string $provider,#[\SensitiveParameter] string $envelope): Secrets
    {
        try{
            $data=json_decode($envelope,true,8,JSON_THROW_ON_ERROR);
            if(!is_array($data)||($data['v']??null)!==1||!is_string($data['key']??null)){throw new \RuntimeException();}
            $parts=[];
            foreach(['nonce','tag','cipher'] as $name){$value=$data[$name]??null;if(!is_string($value)||($decoded=base64_decode($value,true))===false){throw new \RuntimeException();}$parts[$name]=$decoded;}
            if(strlen($parts['nonce'])!==12||strlen($parts['tag'])!==16||strlen($parts['cipher'])<1||strlen($parts['cipher'])>65536){throw new \RuntimeException();}
            $plain=openssl_decrypt($parts['cipher'],'aes-256-gcm',$this->ring->key($data['key']),OPENSSL_RAW_DATA,$parts['nonce'],$parts['tag'],$this->aad($merchant,$connection,$provider,$data['key']));
            if($plain===false){throw new \RuntimeException();}
            $values=json_decode($plain,true,8,JSON_THROW_ON_ERROR);
            if(!is_array($values)){throw new \RuntimeException();}
            $checked=[];foreach($values as $name=>$value){if(!is_string($name)||!is_string($value)){throw new \RuntimeException();}$checked[$name]=$value;}
            return new Secrets($checked);
        }catch(\Throwable){throw new \RuntimeException('Credentials unavailable.');}
    }
    private function aad(string $merchant,string $connection,string $provider,string $key): string
    {
        Id::bytes($merchant);Id::bytes($connection);
        return json_encode(['ordely.credentials',1,'aes-256-gcm',$merchant,$connection,$provider,$key],JSON_THROW_ON_ERROR);
    }
}
