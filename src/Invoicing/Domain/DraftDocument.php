<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Domain;

use Ordely\Core\Data\CommercialLine;
use Ordely\Core\Value\{Currency,ExternalId,Money,Quantity};
use Ordely\Shared\Id;

final readonly class DraftDocument
{
    public Money $net;
    public Money $tax;
    public Money $total;
    /** @param list<CommercialLine> $lines */
    private function __construct(
        public string $reference,public string $customerName,public string $customerAddress,
        public string $customerTaxId,public Currency $currency,public array $lines,
    ) {
        $net=new Money(0,$currency);$tax=new Money(0,$currency);
        foreach($lines as $line){$net=$net->plus($line->unitNet->times($line->quantity->value)->minus($line->discountNet));$tax=$tax->plus($line->tax);}
        $this->net=$net;$this->tax=$tax;$this->total=$net->plus($tax);
    }
    /** @param array<string,mixed> $data */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        try{
            self::keys($data,['reference','customerName','customerAddress','customerTaxId','currency','lines']);
            $code=self::text($data,'currency',3);
            if(!in_array($code,['RON','EUR'],true)){throw new \InvalidArgumentException('Unsupported draft currency.');}
            $currency=new Currency($code,2);$raw=$data['lines']??null;
            if(!is_array($raw)||!array_is_list($raw)||count($raw)<1||count($raw)>50){throw new \InvalidArgumentException('A draft requires 1 to 50 lines.');}
            $lines=[];$seen=[];
            foreach($raw as $item){
                if(!is_array($item)){throw new \InvalidArgumentException('Invalid line.');}
                self::keys($item,['id','description','quantity','unitNet','discountNet','tax']);
                $id=self::text($item,'id',32);Id::bytes($id);
                if(isset($seen[$id])){throw new \InvalidArgumentException('Duplicate line.');}$seen[$id]=true;
                $quantity=$item['quantity']??null;if(!is_int($quantity)){throw new \InvalidArgumentException('Invalid quantity.');}
                $lines[]=new CommercialLine(new ExternalId($id),self::text($item,'description',255),new Quantity($quantity),
                    Money::decimal(self::text($item,'unitNet',32),$currency),Money::decimal(self::text($item,'discountNet',32),$currency),Money::decimal(self::text($item,'tax',32),$currency));
            }
            return new self(self::text($data,'reference',160),self::text($data,'customerName',160),self::text($data,'customerAddress',500,true),self::text($data,'customerTaxId',40,true),$currency,$lines);
        }catch(\OverflowException){throw new \InvalidArgumentException('Draft amount outside supported range.');}
    }
    /** Canonical editable document. Computed totals are never accepted from a client.
     * @return array<string,mixed> */
    public function data(): array
    {
        return ['reference'=>$this->reference,'customerName'=>$this->customerName,'customerAddress'=>$this->customerAddress,'customerTaxId'=>$this->customerTaxId,'currency'=>$this->currency->code,
            'lines'=>array_map(static fn(CommercialLine $line):array=>['id'=>$line->id->value,'description'=>$line->description,'quantity'=>$line->quantity->value,'unitNet'=>$line->unitNet->format(),'discountNet'=>$line->discountNet->format(),'tax'=>$line->tax->format()],$this->lines)];
    }
    /** @return array{net:string,tax:string,total:string,currency:string} */
    public function totals(): array { return ['net'=>$this->net->format(),'tax'=>$this->tax->format(),'total'=>$this->total->format(),'currency'=>$this->currency->code]; }
    /** @param array<array-key,mixed> $data
     * @param list<string> $allowed */
    private static function keys(array $data,array $allowed): void
    {
        if(array_diff(array_keys($data),$allowed)!==[]){throw new \InvalidArgumentException('Unexpected draft field.');}
    }
    /** @param array<array-key,mixed> $data */
    private static function text(#[\SensitiveParameter] array $data,string $key,int $maximum,bool $optional=false): string
    {
        $value=$data[$key]??($optional?'':null);
        if(!is_string($value)||mb_strlen($value)>$maximum||preg_match('/[\x00-\x1f\x7f]/',$value)){throw new \InvalidArgumentException('Invalid draft field.');}
        $value=trim($value);if(!$optional&&$value===''){throw new \InvalidArgumentException('Missing draft field.');}return $value;
    }
}
