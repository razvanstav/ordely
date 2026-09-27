<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class TaxRate
{
    public function __construct(public int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > 10000) { throw new \InvalidArgumentException('Invalid tax rate.'); }
    }
    public function on(Money $net): Money { return $net->ratio($this->basisPoints, 10000); }
}
