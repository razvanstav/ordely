<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class FulfillmentRequest
{
    /** @param list<LineAllocation> $lines */
    public function __construct(public ExternalId $orderId, public array $lines, public ExternalId $locationId, public TrackingUpdate $tracking)
    {
        if ($lines === []) { throw new \InvalidArgumentException('Fulfillment requires lines.'); }
        $ids = array_map(fn (LineAllocation $line): string => $line->lineId->value,$lines);
        if (count(array_unique($ids)) !== count($ids)) { throw new \InvalidArgumentException('Duplicate fulfillment line.'); }
    }
}
