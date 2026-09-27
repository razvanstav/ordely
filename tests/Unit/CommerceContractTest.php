<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Fake\FakeCommerce;
use Ordely\Core\Contracts\{Capability,CapabilitySet,CommerceConnector,ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
use Ordely\Tests\Support\ContractFixtures as F;
use PHPUnit\Framework\TestCase;

final class CommerceContractTest extends TestCase
{
    private function adapter(ConnectionContext $context): CommerceConnector
    {
        $fake=new FakeCommerce();
        $fake->seed($context,[F::order('order-1'),F::order('order-2')],[new D\ProductSnapshot(new V\ExternalId('product-1'),'Tricou test')],[new D\VariantSnapshot(new V\ExternalId('variant-1'),new V\ExternalId('product-1'),'TEST-SKU','Test M',F::money(5000))],[new D\InventorySnapshot(new V\ExternalId('variant-1'),new V\ExternalId('location-1'),5,new \DateTimeImmutable())]);
        return $fake;
    }
    public function testReadsReturnNormalizedTypesAndBoundedPages(): void
    {
        $context=F::context();$adapter=$this->adapter($context);
        $page=$adapter->getOrders($context,new V\PageRequest(1)); self::assertCount(1,$page->items);self::assertNotNull($page->nextCursor);
        $last=$adapter->getOrders($context,new V\PageRequest(1,$page->nextCursor));self::assertNull($last->nextCursor);self::assertSame('order-2',$last->items[0]->id->value);
        self::assertSame(12790,$adapter->getOrder($context,new V\ExternalId('order-1'))->total->minor);
        self::assertSame('Test customer',$adapter->getCustomer($context,new V\ExternalId('customer-1'))->name);
        self::assertCount(1,$adapter->getProducts($context,new V\PageRequest())->items);
        self::assertCount(1,$adapter->searchProducts($context,'tricou',new V\PageRequest())->items);
        self::assertSame('Tricou test',$adapter->getProduct($context,new V\ExternalId('product-1'))->title);
        self::assertCount(1,$adapter->getVariants($context,new V\ExternalId('product-1'),new V\PageRequest())->items);
        self::assertSame(5,$adapter->getInventory($context,[new V\ExternalId('variant-1')],new V\ExternalId('location-1'))[0]->available);
        self::assertSame([],$adapter->getOrders($context,new V\PageRequest(),new \DateTimeImmutable('2027-01-01'))->items);
    }
    public function testForeignContextCannotReadSameExternalIdsOrReuseCursor(): void
    {
        $context=F::context();$adapter=$this->adapter($context);$page=$adapter->getOrders($context,new V\PageRequest(1));
        foreach([F::context(),new ConnectionContext($context->merchant,V\StoreId::new(),$context->connection,V\CorrelationId::new()),new ConnectionContext($context->merchant,$context->store,V\ConnectionId::new(),V\CorrelationId::new())] as $foreign){
            self::assertSame([],$adapter->getOrders($foreign,new V\PageRequest())->items);
            try{$adapter->getOrder($foreign,new V\ExternalId('order-1'));self::fail('Expected foreign reference rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::NotFound,$error->category);}
            try{$adapter->getOrders($foreign,new V\PageRequest(1,$page->nextCursor));self::fail('Expected foreign cursor rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Validation,$error->category);}
        }
    }
    public function testWritesAreIdempotentAndUnsupportedCapabilitiesFail(): void
    {
        $context=F::context();$adapter=$this->adapter($context);$order=new V\ExternalId('order-1');$key=new V\OperationKey('fulfill-1');
        $request=new D\FulfillmentRequest($order,F::allocations(),new V\ExternalId('location-1'),F::tracking());
        $result=$adapter->createFulfillment($context,$request,$key);self::assertSame($result,$adapter->createFulfillment($context,$request,$key));
        self::assertSame('TEST-123',$adapter->updateTracking($context,$result->id,F::tracking(),new V\OperationKey('track-1'))->tracking->number->value);
        self::assertSame('Operator note',$adapter->updateOrder($context,$order,new D\OrderChangeSet('Operator note'),new V\OperationKey('edit-1'))->note);
        self::assertTrue($adapter->addOrderMetadata($context,$order,new D\OrderMetadata(['ordely.operation_id'=>str_repeat('a',32)]),new V\OperationKey('meta-1'))->accepted);
        $return=$adapter->createReturn($context,new D\ReturnRequest($order,F::allocations(),'Test reason'),new V\OperationKey('return-1'));
        self::assertSame(D\ReturnState::Approved,$adapter->updateReturn($context,$return->id,D\ReturnState::Approved,new V\OperationKey('return-update'))->state);
        self::assertCount(1,$adapter->registerWebhooks($context,[new D\WebhookSubscription('order.updated','https://example.test/webhooks')],new V\OperationKey('webhooks-1'))->subscriptions);
        $limited=new FakeCommerce(features:new CapabilitySet([Capability::ReadOrders]));
        self::assertFalse($limited->capabilities($context)->has(Capability::NativeReturns));
        $this->expectException(ProviderFailure::class);$limited->createReturn($context,new D\ReturnRequest($order,F::allocations(),'Test'),new V\OperationKey('no-return'));
    }
    public function testChangedPayloadWithSameOperationKeyIsAConflict(): void
    {
        $context=F::context();$adapter=$this->adapter($context);$order=new V\ExternalId('order-1');$key=new V\OperationKey('note-1');
        $adapter->updateOrder($context,$order,new D\OrderChangeSet('One'),$key);
        try{$adapter->updateOrder($context,$order,new D\OrderChangeSet('Two'),$key);self::fail('Expected conflict.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Conflict,$error->category);}
        self::assertSame('One',$adapter->getOrder($context,$order)->note);
    }
    public function testInvalidExternalLineIsRejectedBeforeEffect(): void
    {
        $context=F::context();$adapter=$this->adapter($context);
        $this->expectException(ProviderFailure::class);
        $adapter->createFulfillment($context,new D\FulfillmentRequest(new V\ExternalId('order-1'),[new D\LineAllocation(new V\ExternalId('foreign-line'),new V\Quantity(1))],new V\ExternalId('location-1'),F::tracking()),new V\OperationKey('invalid-line'));
    }
}
