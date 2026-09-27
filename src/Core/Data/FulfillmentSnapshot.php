<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class FulfillmentSnapshot
{
    public function __construct(public ExternalId $id, public ExternalId $orderId, public TrackingUpdate $tracking) {}
}
