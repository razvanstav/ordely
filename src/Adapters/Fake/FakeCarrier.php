<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Contracts\{Capability,CapabilitySet,CarrierProvider,ConnectionContext,ErrorCategory,Page,ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;

final class FakeCarrier implements CarrierProvider
{
    /** @var array<string,array<string,D\ShipmentSnapshot>> */
    private array $shipments=[];
    /** @var array<string,array<string,D\ActionResult>> */
    private array $pickups=[];
    private readonly CapabilitySet $features;
    public function __construct(public readonly Effects $effects=new Effects(),?CapabilitySet $features=null)
    {
        $this->features=$features ?? new CapabilitySet([Capability::Shipment,Capability::ReturnShipment,Capability::CancelShipment,Capability::Label,Capability::TrackingRead,Capability::Cod,Capability::Pickup,Capability::MultiplePackages,Capability::Rates,Capability::NativeIdempotency],[new V\Currency('RON',2)],['RO']);
    }
    public function capabilities(ConnectionContext $context): CapabilitySet { return $this->features; }
    /** @return list<D\ServiceOption> */
    public function getServices(ConnectionContext $context,string $country): array
    {
        return in_array($country,$this->features->countries,true)?[new D\ServiceOption(new V\ExternalId('standard'),'Test standard service',$this->features)]:[];
    }
    public function calculateRate(ConnectionContext $context,D\ShipmentRequest $request): D\RateQuote
    {
        $this->features->require(Capability::Rates); $this->validate($request);
        return new D\RateQuote($request->service,new V\Money(1900,$request->cod->currency),new \DateTimeImmutable('+10 minutes'));
    }
    public function createShipment(ConnectionContext $context,D\ShipmentRequest $request,V\OperationKey $key): D\ShipmentSnapshot
    {
        $this->features->require(Capability::Shipment); return $this->create($context,$request,$key,'shipment.create');
    }
    public function createReturnShipment(ConnectionContext $context,D\ShipmentRequest $request,V\OperationKey $key): D\ShipmentSnapshot
    {
        $this->features->require(Capability::ReturnShipment); return $this->create($context,$request,$key,'shipment.return');
    }
    private function create(ConnectionContext $context,D\ShipmentRequest $request,V\OperationKey $key,string $operation): D\ShipmentSnapshot
    {
        $this->validate($request);
        return $this->effects->once($context,$operation,$key,$request,D\ShipmentSnapshot::class,function()use($context,$request):D\ShipmentSnapshot{
            $id=new V\ExternalId('test-awb-'.bin2hex(random_bytes(8)));
            return $this->shipments[$context->scope()][$id->value]=new D\ShipmentSnapshot($id,$id,D\ShipmentState::Created,$request->cod);
        });
    }
    private function validate(D\ShipmentRequest $request): void
    {
        if($request->cod->minor>0){$this->features->require(Capability::Cod);}
        if($request->parcelExchange){$this->features->require(Capability::ParcelExchange);}
        if(count($request->parcels)>1){$this->features->require(Capability::MultiplePackages);}
        if(!$this->features->supportsCurrency($request->cod->currency) || !in_array($request->recipient->country,$this->features->countries,true) || !in_array($request->sender->country,$this->features->countries,true) || $request->service->value!=='standard') {throw new ProviderFailure(ErrorCategory::Unsupported);}
    }
    public function getShipment(ConnectionContext $context,V\ExternalId $shipment): D\ShipmentSnapshot
    {
        return $this->shipments[$context->scope()][$shipment->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
    }
    public function cancelShipment(ConnectionContext $context,V\ExternalId $shipment,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::CancelShipment);
        return $this->effects->once($context,'shipment.cancel',$key,$shipment,D\ActionResult::class,function()use($context,$shipment):D\ActionResult{
            $old=$this->getShipment($context,$shipment);
            $this->shipments[$context->scope()][$shipment->value]=new D\ShipmentSnapshot($old->id,$old->trackingNumber,D\ShipmentState::Cancelled,$old->cod);
            return new D\ActionResult($shipment,true);
        });
    }
    public function getLabel(ConnectionContext $context,V\ExternalId $shipment,string $format): D\Document
    {
        $this->features->require(Capability::Label);$this->getShipment($context,$shipment);
        return match($format){'pdf'=>Documents::pdf(),'zpl'=>new D\Document('^XA^FO40,40^ADN,36,20^FDORDELY TEST - NOT VALID^FS^XZ','application/zpl'),default=>throw new ProviderFailure(ErrorCategory::Unsupported)};
    }
    /** @param list<V\ExternalId> $shipments
     * @return list<D\TrackingSnapshot> */
    public function getTracking(ConnectionContext $context,array $shipments): array
    {
        $this->features->require(Capability::TrackingRead);
        return array_map(fn(V\ExternalId $id):D\TrackingSnapshot=>new D\TrackingSnapshot($id,$this->getShipment($context,$id)->state,new \DateTimeImmutable()),$shipments);
    }
    public function createPickup(ConnectionContext $context,D\PickupRequest $request,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::Pickup);
        return $this->effects->once($context,'pickup.create',$key,$request,D\ActionResult::class,function()use($context,$request):D\ActionResult{
            foreach($request->shipments as $id){if($this->getShipment($context,$id)->state===D\ShipmentState::Cancelled){throw new ProviderFailure(ErrorCategory::Validation);}}
            $result=new D\ActionResult(new V\ExternalId('test-pickup-'.bin2hex(random_bytes(8))),true);
            return $this->pickups[$context->scope()][$result->reference->value]=$result;
        });
    }
    public function cancelPickup(ConnectionContext $context,V\ExternalId $pickup,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::Pickup);
        return $this->effects->once($context,'pickup.cancel',$key,$pickup,D\ActionResult::class,function()use($context,$pickup):D\ActionResult{
            if(!isset($this->pickups[$context->scope()][$pickup->value])){throw new ProviderFailure(ErrorCategory::NotFound);}
            unset($this->pickups[$context->scope()][$pickup->value]);return new D\ActionResult($pickup,true);
        });
    }
    /** @return Page<D\PickupPoint> */
    public function getPickupPoints(ConnectionContext $context,string $country,string $city,V\PageRequest $page): Page
    {
        $this->features->require(Capability::PickupPoints);
        $items=in_array($country,$this->features->countries,true)?[new D\PickupPoint(new V\ExternalId('test-point'),'Test pickup point',new V\Address('Test point',$country,$city,'Test street 1','000000'))]:[];
        return Pagination::slice($context,'points:'.$country.':'.$city,$items,$page);
    }
}
