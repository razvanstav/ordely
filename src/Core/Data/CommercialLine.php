<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money, Quantity};
final readonly class CommercialLine
{
    public Money $total;
    public function __construct(public ExternalId $id, public string $description, public Quantity $quantity, public Money $unitNet, public Money $discountNet, public Money $tax)
    {
        if (trim($description) === '' || mb_strlen($description) > 255 || $unitNet->minor < 0 || $discountNet->minor < 0 || $tax->minor < 0) { throw new \InvalidArgumentException('Invalid commercial line.'); }
        $net = $unitNet->times($quantity->value)->minus($discountNet);
        if ($net->minor < 0) { throw new \InvalidArgumentException('Discount exceeds line net.'); }
        $this->total = $net->plus($tax);
    }
}
