<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\Address;
final readonly class CompanyDetails
{
    public function __construct(public string $legalName, public string $taxId, public Address $address)
    {
        if (trim($legalName) === '' || mb_strlen($legalName) > 160 || !preg_match('/^[A-Za-z0-9 .-]{1,40}$/D',$taxId)) { throw new \InvalidArgumentException('Invalid company snapshot.'); }
    }
}
