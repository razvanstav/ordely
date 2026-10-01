<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\PriceBasis;
final readonly class InvoiceDetails
{
    public function __construct(public string $issuedOn,public string $dueOn,public string $sellerVatStatus,public PriceBasis $basis,public string $orderReference)
    {
        foreach([$issuedOn,$dueOn] as $date){if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$parts)||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])){throw new \InvalidArgumentException('Invalid invoice date.');}}
        if($dueOn<$issuedOn||!in_array($sellerVatStatus,['registered','not_registered'],true)||trim($orderReference)===''||mb_strlen($orderReference)>160||preg_match('/[\x00-\x1f\x7f]/',$orderReference)){throw new \InvalidArgumentException('Invalid fiscal document details.');}
    }
}
