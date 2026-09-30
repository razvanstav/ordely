<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;

use Ordely\Invoicing\Domain\OrderPreparation;
use Ordely\Tests\Support\PreparationFixtures as F;
use PHPUnit\Framework\TestCase;

final class OrderPreparationTest extends TestCase
{
    public function testCmsFactsAreKeptWithoutInventingFiscalDataOrNetPrices(): void
    {
        $result=OrderPreparation::build(F::order(),['companyName'=>'Synthetic seller','companyId'=>'TEST009','series'=>'TEST','needsVerification'=>false,'connectionId'=>'PRIVATE-OMIT']);
        self::assertSame('SYNTHETIC-PREP-COMPANY',$result['customer']['name']);self::assertSame('SYNTHETIC-PREP-CLIENT',$result['customer']['contactName']);
        self::assertSame('SYNTHETIC-PREP-STREET',$result['customer']['address']['street']);self::assertNull($result['customer']['taxId']);self::assertNull($result['customer']['type']);
        self::assertSame('tax_inclusive',$result['priceBasis']);self::assertSame('13.10',$result['lines'][0]['prices']['originalUnitPrice']['decimal']);
        self::assertSame('24.20',$result['lines'][0]['prices']['lineDiscountedTotal']['decimal']);self::assertSame('4.20',$result['lines'][0]['taxes'][0]['amount']['decimal']);
        self::assertNull($result['lines'][0]['taxTreatment']);self::assertNull($result['lines'][0]['unit']);self::assertFalse($result['canIssue']);
        self::assertContains('customer_tax_id_missing',array_column($result['issues'],'code'));self::assertNotContains('profile_needs_verification',array_column($result['issues'],'code'));
        self::assertArrayNotHasKey('connectionId',$result['seller']);self::assertArrayNotHasKey('unitNet',$result['lines'][0]);
    }
    public function testMissingBillingNeverFallsBackToShippingOrPersonalIdentity(): void
    {
        $order=F::order();$order['billingAddress']=null;$result=OrderPreparation::build($order,null);
        self::assertNull($result['customer']['name']);self::assertNull($result['customer']['address']['street']);
        self::assertContains('billing_address_missing',array_column($result['issues'],'code'));self::assertContains('profile_missing',array_column($result['issues'],'code'));
        self::assertStringNotContainsString('DO-NOT-USE-SHIPPING',json_encode($result,JSON_THROW_ON_ERROR));
        $order=F::order();$order['billingAddress']['company']=null;$result=OrderPreparation::build($order,null);
        self::assertSame('SYNTHETIC-PREP-CLIENT',$result['customer']['name']);self::assertNull($result['customer']['type']);
        self::assertNotContains('customer_tax_id_missing',array_column($result['issues'],'code'));
    }
    public function testChangedCancelledForeignCurrencyAndShippingHaveExplicitBlockers(): void
    {
        $order=F::order();$order['cancelledAt']='2026-09-30T10:00:01Z';$order['totals']['refunded']=F::money('1');$order['totals']['shipping']=F::money('1900');
        $order['lines'][0]['currentQuantity']=1;$order['lines'][0]['originalUnitPrice']=F::money('100','EUR');
        $result=OrderPreparation::build($order,['needsVerification'=>true]);$codes=array_column($result['issues'],'code');
        foreach(['order_cancelled','order_changed','quantity_requires_review','currency_mismatch','shipping_tax_details_missing','profile_needs_verification'] as $code){self::assertContains($code,$codes);}
    }
    public function testExactLargeMinorUnitsAndPriceBasisRoundTripWithoutFloats(): void
    {
        $order=F::order();$order['taxesIncluded']=false;$order['totals']['current']=F::money('9000000000000000');
        $result=OrderPreparation::build($order,null);self::assertSame('90000000000000.00',$result['totals']['current']['decimal']);self::assertSame('9000000000000000',$result['totals']['current']['minor']);self::assertSame('tax_exclusive',$result['priceBasis']);
        $order['totals']['current']=F::money('100','EUR');self::assertContains('exchange_rate_missing',array_column(OrderPreparation::build($order,null)['issues'],'code'));
    }
    public function testMalformedAmountsAndDuplicateLinesAreRejectedWithSafeErrors(): void
    {
        foreach([0.1,'9000000000000001','9223372036854775807','1e2','-1.1'] as $value){
            $order=F::order();$order['totals']['current']['minor']=$value;
            try{OrderPreparation::build($order,null);self::fail('Expected rejection.');}catch(\InvalidArgumentException $error){self::assertStringNotContainsString('SYNTHETIC-PREP',$error->getMessage());}
        }
        $order=F::order();$order['lines'][]=$order['lines'][0];$this->expectException(\InvalidArgumentException::class);OrderPreparation::build($order,null);
    }
    public function testMissingSourceFieldsAndWrongPrecisionAreReportedRatherThanDefaulted(): void
    {
        $order=F::order();unset($order['taxesIncluded'],$order['lines'][0]['taxes'],$order['lines'][0]['discountAllocations'],$order['lines'][0]['originalUnitPrice']);
        $order['lines'][0]['title']='';$order['totals']['current']['exponent']=3;
        $result=OrderPreparation::build($order,null);$codes=array_column($result['issues'],'code');
        foreach(['price_basis_missing','line_taxes_missing','line_discounts_missing','line_amount_missing','line_description_missing','currency_unsupported','currency_mismatch'] as $code){self::assertContains($code,$codes);}
        self::assertNull($result['lines'][0]['prices']['originalUnitPrice']);self::assertNull($result['lines'][0]['discounts']);
    }
}
