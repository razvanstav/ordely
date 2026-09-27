<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface CarrierProvider extends CapabilitiesProvider
{
    /** @return list<D\ServiceOption> */
    public function getServices(ConnectionContext $context, string $country): array;
    public function calculateRate(ConnectionContext $context, D\ShipmentRequest $request): D\RateQuote;
    public function createShipment(ConnectionContext $context, D\ShipmentRequest $request, V\OperationKey $key): D\ShipmentSnapshot;
    public function createReturnShipment(ConnectionContext $context, D\ShipmentRequest $request, V\OperationKey $key): D\ShipmentSnapshot;
    public function cancelShipment(ConnectionContext $context, V\ExternalId $shipment, V\OperationKey $key): D\ActionResult;
    public function getShipment(ConnectionContext $context, V\ExternalId $shipment): D\ShipmentSnapshot;
    public function getLabel(ConnectionContext $context, V\ExternalId $shipment, string $format): D\Document;
    /** @param list<V\ExternalId> $shipments
     * @return list<D\TrackingSnapshot> */
    public function getTracking(ConnectionContext $context, array $shipments): array;
    public function createPickup(ConnectionContext $context, D\PickupRequest $request, V\OperationKey $key): D\ActionResult;
    public function cancelPickup(ConnectionContext $context, V\ExternalId $pickup, V\OperationKey $key): D\ActionResult;
    /** @return Page<D\PickupPoint> */
    public function getPickupPoints(ConnectionContext $context, string $country, string $city, V\PageRequest $page): Page;
}
