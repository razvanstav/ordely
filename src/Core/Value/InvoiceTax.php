<?php
declare(strict_types=1);
namespace Ordely\Core\Value;

/** Explicit classification and percentage with four decimal places, never inferred from money. */
final readonly class InvoiceTax
{
    public int $scaledRate;
    public function __construct(public string $treatment,public ?string $rate=null,public ?string $reason=null)
    {
        if(!in_array($treatment,['standard','exempt','outside_scope'],true)){throw new \InvalidArgumentException('Invalid tax treatment.');}
        if($treatment==='standard'){
            if($rate===null||!preg_match('/^(100(?:\.0{1,4})?|(?:0|[1-9][0-9]?)(?:\.[0-9]{1,4})?)$/D',$rate)){throw new \InvalidArgumentException('Explicit decimal tax rate required.');}
            $parts=explode('.',$rate);$this->scaledRate=(int)$parts[0]*10000+(int)str_pad($parts[1]??'',4,'0');
        }else{
            if($rate!==null||$reason===null||trim($reason)===''||mb_strlen($reason)>255||preg_match('/[\x00-\x1f\x7f]/',$reason)){throw new \InvalidArgumentException('Explicit tax reason required without a rate.');}$this->scaledRate=0;
        }
    }
    public function on(Money $amount,PriceBasis $basis): Money
    {
        if($amount->minor<0){throw new \InvalidArgumentException('Negative taxable amount.');}
        $numerator=$this->scaledRate;$denominator=1000000+($basis===PriceBasis::TaxInclusive?$numerator:0);
        // Split before multiplication; the remainder product is at most 2e12 on PHP 64-bit.
        $whole=intdiv($amount->minor,$denominator);$remainder=($amount->minor%$denominator)*$numerator;
        $minor=$whole*$numerator+intdiv($remainder,$denominator)+(($remainder%$denominator)*2>=$denominator?1:0);
        return new Money($minor,$amount->currency);
    }
}
