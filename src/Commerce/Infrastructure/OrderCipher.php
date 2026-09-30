<?php
declare(strict_types=1);
namespace Ordely\Commerce\Infrastructure;

use Ordely\Core\Value\CanonicalJson;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\SecretCipher;

final readonly class OrderCipher
{
    public function __construct(private SecretCipher $cipher,private string $purpose='order') {}
    /** @param array<string,mixed> $document */
    public function seal(string $merchant,string $store,string $id,array $document): string
    {
        $raw=CanonicalJson::encode($document);
        if (strlen($raw)>2097152) { throw new \InvalidArgumentException('Order exceeds import limit.'); }
        $pieces=str_split($raw,24000);$chunks=[];
        foreach($pieces as $index=>$piece) {
            $values=[];foreach(str_split(base64_encode($piece),4000) as $part=>$value) { $values['part'.$part]=$value; }
            $chunks[]=json_decode($this->cipher->encrypt($merchant,$id,$this->purpose.':'.$store.':'.count($pieces).':'.$index,new Secrets($values)),true,flags:JSON_THROW_ON_ERROR);
        }
        return json_encode(['chunks'=>$chunks],JSON_THROW_ON_ERROR);
    }
    /** @return array<string,mixed> */
    public function open(string $merchant,string $store,string $id,string $envelope): array
    {
        $data=json_decode($envelope,true,16,JSON_THROW_ON_ERROR);$chunks=$data['chunks']??null;
        if (!is_array($chunks) || !array_is_list($chunks) || count($chunks)<1 || count($chunks)>88) { throw new \RuntimeException('Order unavailable.'); }
        $raw='';
        foreach($chunks as $index=>$chunk) {
            $values=$this->cipher->decrypt($merchant,$id,$this->purpose.':'.$store.':'.count($chunks).':'.$index,json_encode($chunk,JSON_THROW_ON_ERROR))->reveal();
            $piece=base64_decode(implode('',$values),true);if($piece===false){throw new \RuntimeException('Order unavailable.');}$raw.=$piece;
        }
        $document=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($document)) { throw new \RuntimeException('Order unavailable.'); }return $document;
    }
}
