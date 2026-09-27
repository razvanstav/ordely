<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money};
final readonly class VariantSnapshot
{
    public function __construct(public ExternalId $id, public ExternalId $productId, public string $sku, public string $title, public Money $price)
    {
        if (mb_strlen($sku) > 100 || trim($title) === '' || mb_strlen($title) > 255 || $price->minor < 0) { throw new \InvalidArgumentException('Invalid variant.'); }
    }
}
