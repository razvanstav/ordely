<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Value\{Currency,Money};

/** Explicit allowlist: no raw provider objects, notes or custom attributes are retained. */
final class ImportNormalizer
{
    public static function id(mixed $value, string $type): string
    {
        if (!is_string($value) || !preg_match('~^gid://shopify/'.preg_quote($type,'~').'/[1-9][0-9]{0,24}$~D',$value)) { throw new ProviderFailure(ErrorCategory::Validation); }
        return $value;
    }
    public static function text(mixed $value, int $limit=255): string
    {
        if (!is_string($value) || mb_strlen($value)>$limit) { throw new ProviderFailure(ErrorCategory::Validation); } return $value;
    }
    public static function date(mixed $value): string
    {
        $value=self::text($value,40);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',$value)) { throw new ProviderFailure(ErrorCategory::Validation); }
        try { return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'); }
        catch (\Exception) { throw new ProviderFailure(ErrorCategory::Validation); }
    }
    public static function integer(mixed $value): int
    {
        if (!is_int($value)) { throw new ProviderFailure(ErrorCategory::Validation); } return $value;
    }
    public static function boolean(mixed $value): bool
    {
        if (!is_bool($value)) { throw new ProviderFailure(ErrorCategory::Validation); } return $value;
    }
    /** @return array{minor:string,currency:string,exponent:int} */
    public static function money(mixed $value, ?string $currency=null): array
    {
        if ($currency===null) {
            if (!is_array($value) || !is_array($value['presentmentMoney']??null)) { throw new ProviderFailure(ErrorCategory::Validation); }
            $currency=self::text($value['presentmentMoney']['currencyCode']??null,3); $value=$value['presentmentMoney']['amount']??null;
        }
        // Supported currencies are explicit; unknown metadata never silently defaults to two decimals.
        $exponent=match($currency) {
            'RON','EUR','USD','GBP','CHF','CAD','AUD','NZD','PLN','CZK','HUF','BGN','SEK','NOK','DKK'=>2,
            'JPY','KRW'=>0, 'KWD','BHD','JOD'=>3,
            default=>throw new ProviderFailure(ErrorCategory::Unsupported),
        };
        $decimal=self::text($value,32);
        if (str_contains($decimal,'.')) { $decimal=rtrim(rtrim($decimal,'0'),'.'); }
        try { $money=Money::decimal($decimal,new Currency($currency,$exponent)); }
        catch (\InvalidArgumentException|\OverflowException) { throw new ProviderFailure(ErrorCategory::Validation); }
        return ['minor'=>(string)$money->minor,'currency'=>$currency,'exponent'=>$exponent];
    }
    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    public static function product(array $node): array
    {
        return ['kind'=>'product','externalId'=>self::id($node['id']??null,'Product'),'parentId'=>null,
            'title'=>self::text($node['title']??null),'status'=>self::text($node['status']??null,32),'updatedAt'=>self::date($node['updatedAt']??null)];
    }
    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    public static function variant(array $node,string $parent,string $currency): array
    {
        $options=[];
        foreach(self::nodes($node['selectedOptions']??null) as $option) { $options[]=['name'=>self::text($option['name']??null),'value'=>self::text($option['value']??null)]; }
        return ['kind'=>'variant','externalId'=>self::id($node['id']??null,'ProductVariant'),'parentId'=>$parent,
            'title'=>self::text($node['title']??null),'sku'=>self::text($node['sku']??''),'price'=>self::money($node['price']??null,$currency),
            'options'=>$options,'inventoryPolicy'=>self::text($node['inventoryPolicy']??null,32),
            'tracked'=>self::boolean($node['inventoryItem']['tracked']??null),'inventoryItemId'=>self::id($node['inventoryItem']['id']??null,'InventoryItem'),
            'updatedAt'=>self::date($node['updatedAt']??null)];
    }
    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    public static function inventory(array $node,string $variant,bool $tracked): array
    {
        $location=self::id($node['location']['id']??null,'Location');$available=null;
        foreach(self::nodes($node['quantities']??null) as $quantity) { if (($quantity['name']??null)==='available') { $available=self::integer($quantity['quantity']??null); } }
        if ($tracked && $available===null) { throw new ProviderFailure(ErrorCategory::Validation); }
        return ['kind'=>'inventory','externalId'=>$variant.'@'.substr($location,strrpos($location,'/')+1),'parentId'=>$variant,
            'locationId'=>$location,'locationName'=>self::text($node['location']['name']??null),'locationActive'=>self::boolean($node['location']['isActive']??null),
            'available'=>$tracked?$available:null,'tracked'=>$tracked,'updatedAt'=>null,'observedAt'=>gmdate('Y-m-d\TH:i:s\Z')];
    }
    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    public static function order(array $node): array
    {
        $totals=[];
        foreach(['original'=>'totalPriceSet','current'=>'currentTotalPriceSet','discount'=>'currentTotalDiscountsSet','tax'=>'currentTotalTaxSet',
            'shipping'=>'currentShippingPriceSet','received'=>'totalReceivedSet','refunded'=>'totalRefundedSet','outstanding'=>'totalOutstandingSet'] as $name=>$field) {
            $totals[$name]=self::money($node[$field]??null);
        }
        $lines=[];
        foreach(self::nodes($node['lineItems']['nodes']??null) as $line) {
            $discounts=[];$taxes=[];
            foreach(self::nodes($line['discountAllocations']??null) as $discount) { $discounts[]=self::money($discount['allocatedAmountSet']??null); }
            foreach(self::nodes($line['taxLines']??null) as $tax) { $taxes[]=['title'=>self::text($tax['title']??null),'amount'=>self::money($tax['priceSet']??null)]; }
            $quantity=self::integer($line['quantity']??null);$current=self::integer($line['currentQuantity']??null);$refundable=self::integer($line['refundableQuantity']??null);
            if ($quantity<0 || $current<0 || $current>$quantity || $refundable<0 || $refundable>$quantity) { throw new ProviderFailure(ErrorCategory::Validation); }
            $lines[]=['externalId'=>self::id($line['id']??null,'LineItem'),'title'=>self::text($line['title']??null),'sku'=>self::text($line['sku']??''),
                'variantTitle'=>self::text($line['variantTitle']??''),'variantId'=>isset($line['variant']['id'])?self::id($line['variant']['id'],'ProductVariant'):null,
                'productId'=>isset($line['product']['id'])?self::id($line['product']['id'],'Product'):null,
                'quantity'=>$quantity,'currentQuantity'=>$current,'refundableQuantity'=>$refundable,'requiresShipping'=>self::boolean($line['requiresShipping']??null),
                'originalUnitPrice'=>self::money($line['originalUnitPriceSet']??null),'originalTotal'=>self::money($line['originalTotalSet']??null),
                'lineDiscountedTotal'=>self::money($line['discountedTotalSet']??null),'discountAllocations'=>$discounts,'taxes'=>$taxes];
        }
        return ['kind'=>'order','externalId'=>self::id($node['id']??null,'Order'),'parentId'=>null,'number'=>self::text($node['name']??null),
            'createdAt'=>self::date($node['createdAt']??null),'updatedAt'=>self::date($node['updatedAt']??null),
            'cancelledAt'=>isset($node['cancelledAt'])?self::date($node['cancelledAt']):null,'paymentStatus'=>self::text($node['displayFinancialStatus']??'UNKNOWN',40),
            'fulfillmentStatus'=>self::text($node['displayFulfillmentStatus']??'UNKNOWN',40),'taxesIncluded'=>self::boolean($node['taxesIncluded']??null),
            'test'=>self::boolean($node['test']??null),'totals'=>$totals,'lines'=>$lines,
            'customerId'=>isset($node['customer']['id'])?self::id($node['customer']['id'],'Customer'):null,
            'email'=>isset($node['email'])?self::text($node['email'],254):null,'phone'=>isset($node['phone'])?self::text($node['phone'],80):null,
            'shippingAddress'=>self::address($node['shippingAddress']??null),'billingAddress'=>self::address($node['billingAddress']??null)];
    }
    /** @return array<string,string|null>|null */
    private static function address(mixed $value): ?array
    {
        if ($value===null) { return null; } if (!is_array($value)) { throw new ProviderFailure(ErrorCategory::Validation); }
        $result=[];foreach(['name','company','address1','address2','city','provinceCode','zip','countryCodeV2','phone'] as $field) { $result[$field]=isset($value[$field])?self::text($value[$field]):null; }return $result;
    }
    /** @return list<array<string,mixed>> */
    public static function nodes(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) { throw new ProviderFailure(ErrorCategory::Validation); }
        foreach($value as $node) { if (!is_array($node)) { throw new ProviderFailure(ErrorCategory::Validation); } }return $value;
    }
}
