<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
use Ordely\Core\Value\CanonicalJson;
use Ordely\Shared\Id;
final readonly class SafePayload implements \JsonSerializable
{
    /** @param array<string,string|int> $values */
    public function __construct(public array $values=[])
    {
        if(count($values)>20){throw new \InvalidArgumentException('Too many payload references.');}
        foreach($values as $key=>$value){
            if(in_array($key,['entity_id','store_id','connection_id','order_id','shipment_id','invoice_id','return_id','exchange_id','event_id','operation_id','job_id'],true)){
                if(!is_string($value)){throw new \InvalidArgumentException('Reference must be an ID.');}Id::bytes($value);
            }elseif($key==='evidence_sha256'){
                if(!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value)){throw new \InvalidArgumentException('Evidence must be a SHA-256 digest.');}
            }elseif(in_array($key,['version','attempt','count'],true)){
                if(!is_int($value)||$value<0){throw new \InvalidArgumentException('Counter must be non-negative.');}
            }else{throw new \InvalidArgumentException('Payload key is not allowlisted.');}
        }
    }
    public function json(): string { return CanonicalJson::encode((object)$this->values); }
    /** @return array<string,string|int> */
    public function jsonSerialize(): array { return $this->values; }
    public static function fromJson(string $json): self
    {
        $decoded=json_decode($json,false,32,JSON_THROW_ON_ERROR);
        if(!$decoded instanceof \stdClass){throw new \InvalidArgumentException('Payload must be an object.');}
        $values=[];
        foreach(get_object_vars($decoded) as $key=>$value){if(!is_string($value)&&!is_int($value)){throw new \InvalidArgumentException('Invalid payload value.');}$values[$key]=$value;}
        return new self($values);
    }
    public function id(string $key): string
    {
        $value=$this->values[$key] ?? null;if(!is_string($value)){throw new \InvalidArgumentException('Missing payload ID.');}Id::bytes($value);return $value;
    }
}
