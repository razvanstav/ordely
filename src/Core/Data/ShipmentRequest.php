<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{Address, ExternalId, Money, OperationKey, Parcel};
final readonly class ShipmentRequest
{
    /** @param list<Parcel> $parcels
     * @param list<LineAllocation> $contents */
    public function __construct(public Address $sender, public Address $recipient, public array $parcels, public array $contents, public ExternalId $service, public Money $cod, public Money $declaredValue, public OperationKey $clientReference, public bool $parcelExchange = false)
    {
        $cod->assertCurrency($declaredValue);
        if ($parcels === [] || count($parcels) > 100 || $contents === [] || $cod->minor < 0 || $declaredValue->minor < 0) { throw new \InvalidArgumentException('Invalid shipment.'); }
    }
}
