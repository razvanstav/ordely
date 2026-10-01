<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{Address,EmailAddress};

/** Invoice recipient has only a billing address; shipping is a separate commerce fact. */
final readonly class FiscalCustomer
{
    public function __construct(public string $name,public Address $billing,public string $type,public ?string $taxId,public string $vatStatus,public string $region='',public ?EmailAddress $email=null)
    {
        if(trim($name)===''||mb_strlen($name)>160||preg_match('/[\x00-\x1f\x7f]/',$name)||mb_strlen($region)>255||preg_match('/[\x00-\x1f\x7f]/',$region)){throw new \InvalidArgumentException('Invalid invoice recipient.');}
        if(!in_array($type,['individual','company'],true)||!in_array($vatStatus,['registered','not_registered','not_applicable'],true)){throw new \InvalidArgumentException('Invalid recipient classification.');}
        if($type==='company'&&($taxId===null||!preg_match('/^[A-Za-z0-9 .-]{1,40}$/D',$taxId)||$vatStatus==='not_applicable')){throw new \InvalidArgumentException('Company fiscal identity required.');}
        if($type==='individual'&&$taxId!==null){throw new \InvalidArgumentException('Personal fiscal identifiers are not collected.');}
    }
}
