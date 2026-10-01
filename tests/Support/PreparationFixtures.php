<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;

final class PreparationFixtures
{
    /** @return array<string,mixed> */
    public static function fiscalDetails(): array
    {
        return ['document'=>['issuedOn'=>'2026-10-01','dueOn'=>'2026-10-02'],'seller'=>['street'=>'SYNTHETIC-SELLER-STREET','city'=>'Synthetic city','postalCode'=>'000000','country'=>'RO','vatStatus'=>'registered'],'customer'=>['type'=>'company','taxId'=>'SYNTHETIC-TAX-ID','vatStatus'=>'registered'],'lineDefaults'=>['unit'=>'buc','treatment'=>'standard','rate'=>'21.0000'],'lines'=>[]];
    }
    public static function persist(\Ordely\Infrastructure\Database\Sql $db,\Ordely\Identity\Domain\TenantContext $actor,string $store,\Ordely\Integrations\Infrastructure\SecretCipher $cipher): string
    {
        $connection=(new \Ordely\Integrations\Infrastructure\Connections($db,IntegrationFixtures::registry(),$cipher))->create($actor,\Ordely\Shared\Id::new(),'fake-invoice','Synthetic invoice',new \Ordely\Integrations\Domain\Secrets(['apiToken'=>'SYNTHETIC']));
        $run=\Ordely\Shared\Id::new();$order=\Ordely\Shared\Id::new();
        $db->run("INSERT INTO commerce_sync_runs(id,merchant_id,store_id,connection_id,provider_key,status) VALUES(?,?,?,?,'synthetic','completed')",array_map(\Ordely\Shared\Id::bytes(...),[$run,$actor->merchantId,$store,$connection]));
        $envelope=(new \Ordely\Commerce\Infrastructure\OrderCipher($cipher))->seal($actor->merchantId,$store,$order,self::order());
        $db->run("INSERT INTO commerce_records(id,merchant_id,store_id,provider_key,kind,external_id,document,content_hash,version,last_run_id) VALUES(?,?,?,'synthetic','order',?,?,?,3,?)",[\Ordely\Shared\Id::bytes($order),\Ordely\Shared\Id::bytes($actor->merchantId),\Ordely\Shared\Id::bytes($store),$order,$envelope,hash('sha256','synthetic',true),\Ordely\Shared\Id::bytes($run)]);
        return $order;
    }
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
