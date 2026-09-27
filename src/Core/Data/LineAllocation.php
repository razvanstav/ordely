<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Quantity};
final readonly class LineAllocation
{
    public function __construct(public ExternalId $lineId, public Quantity $quantity) {}
}
