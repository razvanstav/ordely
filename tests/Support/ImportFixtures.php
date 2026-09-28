<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;

use Ordely\Core\Contracts\{ConnectionContext,ImportSource};
use Ordely\Core\Data\ImportPage;

final class ImportFixtures implements ImportSource
{
    public bool $omitCatalog=false;
    public int $calls=0;
    public ?\Closure $beforePage=null;
    public ?\Throwable $failure=null;
    public int $total=19000;
    public string $date='2026-09-28T10:00:00.000000Z';
    /** @return array<string,mixed> */
    public function order(int $line): array
    {
        return ['kind'=>'order','externalId'=>'gid://shopify/Order/1','parentId'=>null,'number'=>'#TEST-1','updatedAt'=>$this->date,
            'createdAt'=>$this->date,'cancelledAt'=>null,'test'=>true,'paymentStatus'=>'PAID','fulfillmentStatus'=>'UNFULFILLED',
            'customerId'=>'gid://shopify/Customer/1','email'=>'synthetic@example.test','phone'=>null,'shippingAddress'=>null,'billingAddress'=>null,
            'totals'=>['current'=>['minor'=>(string)$this->total,'currency'=>'RON','exponent'=>2]],
            'lines'=>[['externalId'=>'gid://shopify/LineItem/'.$line,'title'=>'Synthetic item','quantity'=>1,'currentQuantity'=>1]]];
    }
    public function page(ConnectionContext $context,string $collection,?string $parent,?string $cursor,?string $since): ImportPage
    {
        ++$this->calls;if($this->beforePage!==null){($this->beforePage)();}if($this->failure!==null){throw $this->failure;}
        return match($collection) {
            'orders'=>new ImportPage([],null,[['collection'=>'order','parent'=>'gid://shopify/Order/1']]),
            'order'=>new ImportPage([$this->order($cursor===null?1:2)],$cursor===null?'page-two':null),
            'products'=>$this->omitCatalog?new ImportPage([]):new ImportPage([['kind'=>'product','externalId'=>'gid://shopify/Product/1','parentId'=>null,'title'=>'Test product','status'=>'ACTIVE','updatedAt'=>$this->date]],null,[['collection'=>'variants','parent'=>'gid://shopify/Product/1']]),
            'variants'=>new ImportPage([['kind'=>'variant','externalId'=>'gid://shopify/ProductVariant/1','parentId'=>$parent,'title'=>'M','sku'=>'TEST-M','updatedAt'=>$this->date]],null,[['collection'=>'inventory','parent'=>'gid://shopify/ProductVariant/1']]),
            'inventory'=>new ImportPage([['kind'=>'inventory','externalId'=>'gid://shopify/ProductVariant/1@1','parentId'=>$parent,'available'=>-2,'locationId'=>'gid://shopify/Location/1','observedAt'=>gmdate(DATE_ATOM),'updatedAt'=>null]]),
            default=>throw new \LogicException('Unexpected test collection.'),
        };
    }
}
