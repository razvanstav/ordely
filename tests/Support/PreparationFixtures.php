<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;

final class PreparationFixtures
{
    /** @return array{minor:string,currency:string,exponent:int} */
    public static function money(string $minor,string $currency='RON'): array { return ['minor'=>$minor,'currency'=>$currency,'exponent'=>2]; }
    /** @return array<string,mixed> */
    public static function order(): array
    {
        return ['kind'=>'order','externalId'=>'gid://shopify/Order/900009','parentId'=>null,'number'=>'TEST-PREP-09','updatedAt'=>'2026-09-30T10:00:00Z','cancelledAt'=>null,'taxesIncluded'=>true,
            'email'=>'synthetic@example.test','billingAddress'=>['name'=>'SYNTHETIC-PREP-CLIENT','company'=>'SYNTHETIC-PREP-COMPANY','address1'=>'SYNTHETIC-PREP-STREET','address2'=>null,'city'=>'Synthetic city','provinceCode'=>'B','zip'=>'000000','countryCodeV2'=>'RO'],
            'shippingAddress'=>['name'=>'DO-NOT-USE-SHIPPING','address1'=>'DO-NOT-USE-SHIPPING-STREET'],
            'totals'=>['original'=>self::money('2420'),'current'=>self::money('2420'),'discount'=>self::money('200'),'tax'=>self::money('420'),'shipping'=>self::money('0'),'received'=>self::money('2420'),'refunded'=>self::money('0'),'outstanding'=>self::money('0')],
            'lines'=>[['id'=>str_repeat('a',32),'externalId'=>'gid://shopify/LineItem/900009','title'=>'Synthetic item','sku'=>'SYNTHETIC-SKU','quantity'=>2,'currentQuantity'=>2,'originalUnitPrice'=>self::money('1310'),'originalTotal'=>self::money('2620'),'lineDiscountedTotal'=>self::money('2420'),'discountAllocations'=>[self::money('200')],'taxes'=>[['title'=>'TVA','amount'=>self::money('420')]]]]];
    }
}
