<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Fake\{Effects,FakeCarrier};
use Ordely\Core\Contracts\{Capability,CapabilitySet,CarrierProvider,ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
use Ordely\Tests\Support\ContractFixtures as F;
use PHPUnit\Framework\TestCase;

final class CarrierContractTest extends TestCase
{
    private function adapter(): CarrierProvider { return new FakeCarrier(); }
    public function testShipmentLifecycleAndPickupThroughContract(): void
    {
        $adapter=$this->adapter();$ctx=F::context();$request=F::shipment();$key=new V\OperationKey('ship-1');
        self::assertCount(1,$adapter->getServices($ctx,'RO'));self::assertSame([],$adapter->getServices($ctx,'XX'));
        self::assertSame(1900,$adapter->calculateRate($ctx,$request)->gross->minor);
        $shipment=$adapter->createShipment($ctx,$request,$key);self::assertSame($shipment,$adapter->createShipment($ctx,$request,$key));
        self::assertSame(12790,$adapter->getShipment($ctx,$shipment->id)->cod->minor);
        self::assertStringStartsWith('%PDF-',$adapter->getLabel($ctx,$shipment->id,'pdf')->contents());
        self::assertStringStartsWith('^XA',$adapter->getLabel($ctx,$shipment->id,'zpl')->contents());
        self::assertSame(D\ShipmentState::Created,$adapter->getTracking($ctx,[$shipment->id])[0]->state);
        $pickup=$adapter->createPickup($ctx,new D\PickupRequest(F::address(),[$shipment->id],new \DateTimeImmutable('+1 day'),new \DateTimeImmutable('+2 days')),new V\OperationKey('pickup-1'));
        self::assertTrue($adapter->cancelPickup($ctx,$pickup->reference,new V\OperationKey('pickup-cancel'))->accepted);
        self::assertTrue($adapter->cancelShipment($ctx,$shipment->id,new V\OperationKey('cancel-1'))->accepted);
        self::assertSame(D\ShipmentState::Cancelled,$adapter->getShipment($ctx,$shipment->id)->state);
        self::assertNotSame($shipment->id->value,$adapter->createReturnShipment($ctx,F::shipment(0),new V\OperationKey('inbound-1'))->id->value);
    }
    public function testTenantStoreConnectionAndOperationKeyScopes(): void
    {
        $adapter=$this->adapter();$ctx=F::context();$key=new V\OperationKey('same-key');$shipment=$adapter->createShipment($ctx,F::shipment(),$key);
        foreach([F::context(),new ConnectionContext($ctx->merchant,V\StoreId::new(),$ctx->connection,V\CorrelationId::new()),new ConnectionContext($ctx->merchant,$ctx->store,V\ConnectionId::new(),V\CorrelationId::new())] as $other){
            try{$adapter->getLabel($other,$shipment->id,'pdf');self::fail('Expected scope rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::NotFound,$error->category);}
            self::assertNotSame($shipment->id->value,$adapter->createShipment($other,F::shipment(),$key)->id->value);
        }
        try{$adapter->createShipment($ctx,F::shipment(0),$key);self::fail('Expected conflict.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Conflict,$error->category);}
    }
    public function testRestrictedProviderCannotSilentlyAcceptUnsupportedFeatures(): void
    {
        $ctx=F::context();$adapter=new FakeCarrier(features:new CapabilitySet([Capability::Shipment],[new V\Currency('RON',2)],['RO']));
        self::assertSame(0,$adapter->createShipment($ctx,F::shipment(0),new V\OperationKey('zero'))->cod->minor);
        foreach([fn()=>$adapter->createShipment($ctx,F::shipment(),new V\OperationKey('cod')),fn()=>$adapter->createShipment($ctx,F::shipment(0,true),new V\OperationKey('exchange')),fn()=>$adapter->getPickupPoints($ctx,'RO','Test',new V\PageRequest())] as $operation){
            try{$operation();self::fail('Expected unsupported.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Unsupported,$error->category);self::assertFalse($error->retryable());}
        }
    }
    public function testAmbiguousFailurePreservesEffectAndTransientFailureDoesNotPerformIt(): void
    {
        $effects=new Effects();$adapter=new FakeCarrier($effects);$ctx=F::context();$key=new V\OperationKey('timeout');
        $effects->failNext(ErrorCategory::Unknown,afterEffect:true);
        try{$adapter->createShipment($ctx,F::shipment(),$key);self::fail('Expected timeout.');}catch(ProviderFailure $error){self::assertFalse($error->retryable());self::assertSame(ErrorCategory::Unknown,$error->category);}
        $counts=[$effects->performed()];
        $result=$adapter->createShipment($ctx,F::shipment(),$key);$counts[]=$effects->performed();self::assertNotEmpty($result->id->value);
        $effects->failNext(ErrorCategory::Transient);
        try{$adapter->createShipment($ctx,F::shipment(),new V\OperationKey('not-sent'));self::fail('Expected transient error.');}catch(ProviderFailure $error){self::assertTrue($error->retryable());}
        $counts[]=$effects->performed();self::assertSame([1,1,1],$counts);
    }
}
