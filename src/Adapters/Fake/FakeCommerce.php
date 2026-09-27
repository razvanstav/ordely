<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Contracts\{Capability, CapabilitySet, CommerceConnector, ConnectionContext, ErrorCategory, Page, ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;

final class FakeCommerce implements CommerceConnector
{
    /** @var array<string,array<string,D\OrderSnapshot>> */
    private array $orders=[];
    /** @var array<string,array<string,D\ProductSnapshot>> */
    private array $products=[];
    /** @var array<string,array<string,D\VariantSnapshot>> */
    private array $variants=[];
    /** @var array<string,list<D\InventorySnapshot>> */
    private array $inventory=[];
    /** @var array<string,array<string,D\FulfillmentSnapshot>> */
    private array $fulfillments=[];
    /** @var array<string,array<string,D\ReturnSnapshot>> */
    private array $returns=[];
    /** @var array<string,array<string,D\OrderMetadata>> */
    private array $metadata=[];
    /** @var array<string,D\RegistrationReport> */
    private array $subscriptions=[];
    private readonly CapabilitySet $features;
    public function __construct(public readonly Effects $effects=new Effects(), ?CapabilitySet $features=null)
    {
        $this->features=$features ?? new CapabilitySet([Capability::ReadOrders,Capability::ReadCustomer,Capability::ReadCatalog,Capability::ReadInventory,Capability::Fulfillment,Capability::TrackingWrite,Capability::EditOrder,Capability::Metadata,Capability::NativeReturns,Capability::Webhooks,Capability::NativeIdempotency]);
    }
    public function capabilities(ConnectionContext $context): CapabilitySet { return $this->features; }
    public function inspectMetadata(ConnectionContext $context,V\ExternalId $order): ?D\OrderMetadata { return $this->metadata[$context->scope()][$order->value] ?? null; }
    public function inspectSubscriptions(ConnectionContext $context): D\RegistrationReport { return $this->subscriptions[$context->scope()] ?? new D\RegistrationReport([]); }

    /** @param list<D\OrderSnapshot> $orders
     * @param list<D\ProductSnapshot> $products
     * @param list<D\VariantSnapshot> $variants
     * @param list<D\InventorySnapshot> $inventory */
    public function seed(ConnectionContext $context,array $orders=[],array $products=[],array $variants=[],array $inventory=[]): void
    {
        $scope=$context->scope();
        foreach($orders as $order){$this->orders[$scope][$order->id->value]=$order;}
        foreach($products as $product){$this->products[$scope][$product->id->value]=$product;}
        foreach($variants as $variant){$this->variants[$scope][$variant->id->value]=$variant;}
        $this->inventory[$scope]=$inventory;
    }
    public function getOrder(ConnectionContext $context,V\ExternalId $id): D\OrderSnapshot
    {
        $this->features->require(Capability::ReadOrders);
        return $this->orders[$context->scope()][$id->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
    }
    /** @return Page<D\OrderSnapshot> */
    public function getOrders(ConnectionContext $context,V\PageRequest $page,?\DateTimeImmutable $updatedSince=null): Page
    {
        $this->features->require(Capability::ReadOrders); $items=$this->orders[$context->scope()] ?? []; ksort($items,SORT_STRING);
        if($updatedSince!==null){$items=array_filter($items,fn(D\OrderSnapshot $o):bool=>$o->updatedAt >= $updatedSince);}
        return Pagination::slice($context,'orders:'.$updatedSince?->format(DATE_ATOM),array_values($items),$page);
    }
    public function getCustomer(ConnectionContext $context,V\ExternalId $id): D\CustomerSnapshot
    {
        $this->features->require(Capability::ReadCustomer);
        foreach($this->orders[$context->scope()] ?? [] as $order){if($order->customer->id->value===$id->value){return $order->customer;}}
        throw new ProviderFailure(ErrorCategory::NotFound);
    }
    /** @return Page<D\ProductSnapshot> */
    public function getProducts(ConnectionContext $context,V\PageRequest $page): Page { return $this->searchProducts($context,'',$page); }
    /** @return Page<D\ProductSnapshot> */
    public function searchProducts(ConnectionContext $context,string $query,V\PageRequest $page): Page
    {
        $this->features->require(Capability::ReadCatalog);
        if(mb_strlen($query)>200){throw new ProviderFailure(ErrorCategory::Validation);}
        $items=$this->products[$context->scope()] ?? []; ksort($items,SORT_STRING);
        $items=array_values(array_filter($items,fn(D\ProductSnapshot $p):bool=>mb_stripos($p->title,$query)!==false));
        return Pagination::slice($context,'products:'.$query,$items,$page);
    }
    public function getProduct(ConnectionContext $context,V\ExternalId $id): D\ProductSnapshot
    {
        $this->features->require(Capability::ReadCatalog);
        return $this->products[$context->scope()][$id->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
    }
    /** @return Page<D\VariantSnapshot> */
    public function getVariants(ConnectionContext $context,V\ExternalId $product,V\PageRequest $page): Page
    {
        $this->getProduct($context,$product); $items=$this->variants[$context->scope()] ?? []; ksort($items,SORT_STRING);
        return Pagination::slice($context,'variants:'.$product->value,array_values(array_filter($items,fn(D\VariantSnapshot $v):bool=>$v->productId->value===$product->value)),$page);
    }
    /** @param list<V\ExternalId> $variants
     * @return list<D\InventorySnapshot> */
    public function getInventory(ConnectionContext $context,array $variants,V\ExternalId $location): array
    {
        $this->features->require(Capability::ReadInventory); $result=[];
        foreach($variants as $variant){
            $found=false;
            foreach($this->inventory[$context->scope()] ?? [] as $stock){if($stock->variantId->value===$variant->value && $stock->locationId->value===$location->value){$result[]=$stock;$found=true;}}
            if(!$found){throw new ProviderFailure(ErrorCategory::NotFound);}
        }
        return $result;
    }
    public function createFulfillment(ConnectionContext $context,D\FulfillmentRequest $request,V\OperationKey $key): D\FulfillmentSnapshot
    {
        $this->features->require(Capability::Fulfillment);
        return $this->effects->once($context,'fulfillment.create',$key,$request,D\FulfillmentSnapshot::class,function()use($context,$request):D\FulfillmentSnapshot{
            $this->validateLines($this->getOrder($context,$request->orderId),$request->lines);
            $result=new D\FulfillmentSnapshot(new V\ExternalId('ff-'.bin2hex(random_bytes(8))),$request->orderId,$request->tracking);
            return $this->fulfillments[$context->scope()][$result->id->value]=$result;
        });
    }
    public function updateTracking(ConnectionContext $context,V\ExternalId $fulfillment,D\TrackingUpdate $tracking,V\OperationKey $key): D\FulfillmentSnapshot
    {
        $this->features->require(Capability::TrackingWrite);
        return $this->effects->once($context,'tracking.update',$key,[$fulfillment,$tracking],D\FulfillmentSnapshot::class,function()use($context,$fulfillment,$tracking):D\FulfillmentSnapshot{
            $old=$this->fulfillments[$context->scope()][$fulfillment->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
            return $this->fulfillments[$context->scope()][$fulfillment->value]=new D\FulfillmentSnapshot($fulfillment,$old->orderId,$tracking);
        });
    }
    public function updateOrder(ConnectionContext $context,V\ExternalId $order,D\OrderChangeSet $changes,V\OperationKey $key): D\OrderSnapshot
    {
        $this->features->require(Capability::EditOrder);
        return $this->effects->once($context,'order.update',$key,[$order,$changes],D\OrderSnapshot::class,function()use($context,$order,$changes):D\OrderSnapshot{
            $old=$this->getOrder($context,$order);
            return $this->orders[$context->scope()][$order->value]=new D\OrderSnapshot($old->id,$old->number,$old->lines,$old->customer,$old->shippingGross,$old->total,$old->paid,new \DateTimeImmutable(),$changes->note);
        });
    }
    public function addOrderMetadata(ConnectionContext $context,V\ExternalId $order,D\OrderMetadata $metadata,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::Metadata);
        return $this->effects->once($context,'metadata.write',$key,[$order,$metadata],D\ActionResult::class,function()use($context,$order,$metadata):D\ActionResult{
            $this->getOrder($context,$order); $this->metadata[$context->scope()][$order->value]=$metadata; return new D\ActionResult($order,true);
        });
    }
    public function createReturn(ConnectionContext $context,D\ReturnRequest $request,V\OperationKey $key): D\ReturnSnapshot
    {
        $this->features->require(Capability::NativeReturns);
        return $this->effects->once($context,'return.create',$key,$request,D\ReturnSnapshot::class,function()use($context,$request):D\ReturnSnapshot{
            $this->validateLines($this->getOrder($context,$request->orderId),$request->lines);
            $result=new D\ReturnSnapshot(new V\ExternalId('ret-'.bin2hex(random_bytes(8))),$request->orderId,D\ReturnState::Requested);
            return $this->returns[$context->scope()][$result->id->value]=$result;
        });
    }
    public function updateReturn(ConnectionContext $context,V\ExternalId $return,D\ReturnState $state,V\OperationKey $key): D\ReturnSnapshot
    {
        $this->features->require(Capability::NativeReturns);
        return $this->effects->once($context,'return.update',$key,[$return,$state],D\ReturnSnapshot::class,function()use($context,$return,$state):D\ReturnSnapshot{
            $old=$this->returns[$context->scope()][$return->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
            return $this->returns[$context->scope()][$return->value]=new D\ReturnSnapshot($return,$old->orderId,$state);
        });
    }
    /** @param list<D\WebhookSubscription> $subscriptions */
    public function registerWebhooks(ConnectionContext $context,array $subscriptions,V\OperationKey $key): D\RegistrationReport
    {
        $this->features->require(Capability::Webhooks);
        return $this->effects->once($context,'webhooks.register',$key,$subscriptions,D\RegistrationReport::class,fn():D\RegistrationReport=>$this->subscriptions[$context->scope()]=new D\RegistrationReport($subscriptions));
    }
    /** @param list<D\LineAllocation> $lines */
    private function validateLines(D\OrderSnapshot $order,array $lines): void
    {
        foreach($lines as $line){
            $valid=false;
            foreach($order->lines as $original){if($original->id->value===$line->lineId->value && $line->quantity->value<=$original->quantity->value){$valid=true;}}
            if(!$valid){throw new ProviderFailure(ErrorCategory::Validation);}
        }
    }
}
