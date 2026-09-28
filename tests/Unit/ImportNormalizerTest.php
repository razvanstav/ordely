<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Shopify\ImportNormalizer as N;
use Ordely\Core\Contracts\ProviderFailure;
use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Integrations\Infrastructure\{SecretCipher,KeyRing};
use PHPUnit\Framework\TestCase;

final class ImportNormalizerTest extends TestCase
{
    public function testDecimalAmountsNeverUseFloatingPointAndUnsupportedPrecisionFails(): void
    {
        self::assertSame(['minor'=>'1234567890123456','currency'=>'RON','exponent'=>2],N::money('12345678901234.56','RON'));
        self::assertSame('10',N::money('10.00','JPY')['minor']);self::assertSame('1234',N::money('1.234','KWD')['minor']);
        self::assertSame('-2',N::money('-0.02','RON')['minor']);
        foreach([['0.001','RON'],['1.00','XXX'],[1.25,'RON']] as [$value,$currency]) { try {N::money($value,$currency);self::fail();}catch(ProviderFailure $error){self::assertNotSame('',$error->getMessage());} }
    }
    public function testInventoryPreservesNegativeAndUntrackedAsDistinctValues(): void
    {
        $node=['location'=>['id'=>'gid://shopify/Location/1','name'=>'Test','isActive'=>true],'quantities'=>[['name'=>'available','quantity'=>-3]]];
        self::assertSame(-3,N::inventory($node,'gid://shopify/ProductVariant/1',true)['available']);
        self::assertNull(N::inventory($node,'gid://shopify/ProductVariant/1',false)['available']);
        $node['quantities']=[];$this->expectException(ProviderFailure::class);N::inventory($node,'gid://shopify/ProductVariant/1',true);
    }
    public function testMissingAddressesDeletedVariantDiscountsTaxesAndRefundAmountsRemainSourceFacts(): void
    {
        $money=static fn(string $value):array=>['presentmentMoney'=>['amount'=>$value,'currencyCode'=>'RON']];
        $line=['id'=>'gid://shopify/LineItem/1','title'=>'Test','sku'=>null,'variantTitle'=>null,'variant'=>null,'product'=>null,
            'quantity'=>3,'currentQuantity'=>1,'refundableQuantity'=>1,'requiresShipping'=>true,'originalUnitPriceSet'=>$money('10.00'),
            'originalTotalSet'=>$money('30.00'),'discountedTotalSet'=>$money('27.00'),'discountAllocations'=>[['allocatedAmountSet'=>$money('3.00')]],
            'taxLines'=>[['title'=>'Synthetic tax','priceSet'=>$money('5.13')]]];
        $node=['id'=>'gid://shopify/Order/1','name'=>'#TEST','createdAt'=>'2026-09-28T10:00:00Z','updatedAt'=>'2026-09-28T10:00:00Z',
            'displayFinancialStatus'=>'PARTIALLY_REFUNDED','displayFulfillmentStatus'=>'FULFILLED','taxesIncluded'=>true,'test'=>true,
            'lineItems'=>['nodes'=>[$line]],'customer'=>null,'email'=>null,'shippingAddress'=>null,'billingAddress'=>null];
        foreach(['totalPriceSet','currentTotalPriceSet','currentTotalDiscountsSet','currentTotalTaxSet','currentShippingPriceSet','totalReceivedSet','totalRefundedSet','totalOutstandingSet'] as $field){$node[$field]=$money('1.23');}
        $order=N::order($node);self::assertSame('PARTIALLY_REFUNDED',$order['paymentStatus']);self::assertNull($order['shippingAddress']);self::assertNull($order['lines'][0]['variantId']);
        self::assertSame('300',$order['lines'][0]['discountAllocations'][0]['minor']);self::assertSame('513',$order['lines'][0]['taxes'][0]['amount']['minor']);self::assertSame('123',$order['totals']['refunded']['minor']);
        self::assertSame(3,$order['lines'][0]['quantity']);self::assertSame(1,$order['lines'][0]['currentQuantity']);
    }
    public function testLargeEncryptedOrderIsBoundToTenantStoreAndChunkOrder(): void
    {
        $cipher=new OrderCipher(new SecretCipher(new KeyRing('test',['test'=>random_bytes(32)])));$merchant=str_repeat('1',32);$store=str_repeat('2',32);$id=str_repeat('3',32);
        $data=['email'=>'synthetic@example.test','lines'=>array_fill(0,800,['title'=>str_repeat('x',200)])];$envelope=$cipher->seal($merchant,$store,$id,$data);
        self::assertStringNotContainsString('synthetic@example.test',$envelope);self::assertSame($data,$cipher->open($merchant,$store,$id,$envelope));
        try{$cipher->open($merchant,str_repeat('4',32),$id,$envelope);self::fail();}catch(\RuntimeException $error){self::assertSame('Credentials unavailable.',$error->getMessage());}
        $tampered=json_decode($envelope,true,flags:JSON_THROW_ON_ERROR);$tampered['chunks']=array_reverse($tampered['chunks']);
        $this->expectException(\RuntimeException::class);$cipher->open($merchant,$store,$id,json_encode($tampered,JSON_THROW_ON_ERROR));
    }
}
