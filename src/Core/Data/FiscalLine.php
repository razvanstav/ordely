<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId,InvoiceTax,Money,PriceBasis,Quantity};

/** Unit price and discount keep their imported basis; net unit prices are never fabricated. */
final readonly class FiscalLine
{
    public Money $subtotal;public Money $net;public Money $total;
    public function __construct(public ExternalId $id,public string $description,public Quantity $quantity,public string $unit,public Money $unitPrice,public Money $discount,public Money $tax,public PriceBasis $basis,public InvoiceTax $taxTreatment)
    {
        foreach([$description,$unit] as $text){if(trim($text)===''||mb_strlen($text)>255||preg_match('/[\x00-\x1f\x7f]/',$text)){throw new \InvalidArgumentException('Invalid fiscal line text.');}}
        if($unitPrice->minor<0||$discount->minor<0||$tax->minor<0){throw new \InvalidArgumentException('Negative fiscal money.');}
        $this->subtotal=$unitPrice->times($quantity->value)->minus($discount);
        if($this->subtotal->minor<0){throw new \InvalidArgumentException('Discount exceeds original amount.');}
        $expected=$taxTreatment->on($this->subtotal,$basis);$expected->assertCurrency($tax);
        if($expected->minor!==$tax->minor){throw new \InvalidArgumentException('Declared tax differs from imported tax.');}
        $this->net=$basis===PriceBasis::TaxInclusive?$this->subtotal->minus($tax):$this->subtotal;
        $this->total=$basis===PriceBasis::TaxInclusive?$this->subtotal:$this->subtotal->plus($tax);
    }
}
