<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Core\Value\CanonicalJson;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\SecretCipher;
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Shared\Id;

final readonly class DraftCipher
{
    public function __construct(private SecretCipher $cipher) {}
    public function seal(string $merchant,string $store,string $id,int $version,DraftDocument $document): string
    {
        $raw=CanonicalJson::encode($document->data());
        if(strlen($raw)>24000){throw new \InvalidArgumentException('Draft is too large.');}
        $values=[];foreach(str_split(base64_encode($raw),4000) as $index=>$piece){$values['part'.$index]=$piece;}
        return $this->cipher->encrypt($merchant,$id,$this->purpose($store,$version),new Secrets($values));
    }
    public function open(string $merchant,string $store,string $id,int $version,#[\SensitiveParameter] string $envelope): DraftDocument
    {
        try{
            $values=$this->cipher->decrypt($merchant,$id,$this->purpose($store,$version),$envelope)->reveal();
            $raw=base64_decode(implode('',$values),true);if($raw===false||strlen($raw)>24000){throw new \RuntimeException();}
            $data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($data)){throw new \RuntimeException();}
            return DraftDocument::fromArray($data);
        }catch(\Throwable){throw new \RuntimeException('Invoice draft unavailable.');}
    }
    private function purpose(string $store,int $version): string
    {
        Id::bytes($store);if($version<1){throw new \InvalidArgumentException('Invalid revision.');}return 'invoice-draft:'.$store.':'.$version;
    }
}
