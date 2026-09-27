<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class TrackingSnapshot
{
    public function __construct(public ExternalId $shipmentId, public ShipmentState $state, public \DateTimeImmutable $occurredAt) {}
}
