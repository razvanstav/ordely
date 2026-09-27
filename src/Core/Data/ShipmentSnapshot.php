<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money};
final readonly class ShipmentSnapshot
{
    public function __construct(public ExternalId $id, public ExternalId $trackingNumber, public ShipmentState $state, public Money $cod) {}
}
