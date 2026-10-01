<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Core\Data\{FiscalCustomer,FiscalLine,InvoiceDraft};
use Ordely\Core\Value\{Currency,ExternalId,InvoiceTax,Money,OperationKey,PriceBasis,Quantity};
use Ordely\Invoicing\Domain\{FiscalPreparation,InvoiceAssembly};
use Ordely\Tests\Support\{ContractFixtures as C,PreparationFixtures as F};
use PHPUnit\Framework\TestCase;
final class InvoiceAssemblyTest extends TestCase
{
    public function testInclusiveSnapshotBecomesFiscalContractWithoutInventingNetUnitPriceOrShipping(): void
    {
        $snapshot=F::prepared();$fiscal=FiscalPreparation::build($snapshot);$report=InvoiceAssembly::check($snapshot,$fiscal);self::assertSame('RECONCILED',$report['status']);self::assertFalse($report['canIssue']);
        $draft=InvoiceAssembly::draft($snapshot,$fiscal,new OperationKey('test-issue'));self::assertInstanceOf(FiscalCustomer::class,$draft->customer);self::assertInstanceOf(FiscalLine::class,$draft->lines[0]);
        self::assertSame('13.10',$draft->lines[0]->unitPrice->format());self::assertSame(PriceBasis::TaxInclusive,$draft->lines[0]->basis);self::assertSame('20.00',$draft->lines[0]->net->format());self::assertSame('24.20',$draft->total->format());self::assertSame('2026-10-01',$draft->details?->issuedOn);
        self::assertFalse(property_exists($draft->customer,'shipping'));self::assertSame('SYNTHETIC-PREP-STREET',$draft->customer->billing->line);
        self::assertFalse(InvoiceAssembly::check($snapshot,FiscalPreparation::build($snapshot,true))['readyForProvider']);
        unset($snapshot['fiscalDetails']);$report=InvoiceAssembly::check($snapshot,FiscalPreparation::build($snapshot));self::assertSame('INCOMPLETE',$report['status']);self::assertSame('20.00',$report['totals']['net']['decimal']);
    }
    public function testExclusiveBasisAndFractionalTaxUseExactMinorUnitsIncludingRoundingAndLimits(): void
    {
        $snapshot=F::prepared();$snapshot['priceBasis']='tax_exclusive';$line=&$snapshot['lines'][0];$line['prices']['originalUnitPrice']['minor']='1200';$line['prices']['originalTotal']['minor']='2400';$line['prices']['lineDiscountedTotal']['minor']='2000';$line['discounts'][0]['minor']='400';$snapshot['totals']['discount']['minor']='400';
        $draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot),new OperationKey('test-exclusive'));self::assertInstanceOf(FiscalLine::class,$draft->lines[0]);self::assertSame('12.00',$draft->lines[0]->unitPrice->format());self::assertSame('24.20',$draft->total->format());
        $currency=new Currency('RON',2);$fractional=new InvoiceTax('standard','7.1250');self::assertSame(7125,$fractional->on(new Money(100000,$currency),PriceBasis::TaxExclusive)->minor);
        $hundred=new InvoiceTax('standard','100');self::assertSame(2,$hundred->on(new Money(3,$currency),PriceBasis::TaxInclusive)->minor);self::assertSame(Money::MAX,$hundred->on(new Money(Money::MAX,$currency),PriceBasis::TaxExclusive)->minor);
        $this->expectException(\OverflowException::class);new FiscalLine(new ExternalId('limit'),'Synthetic',new Quantity(1),'buc',new Money(Money::MAX,$currency),new Money(0,$currency),new Money(Money::MAX,$currency),PriceBasis::TaxExclusive,$hundred);
    }
    public function testSourceDiscrepanciesCannotBecomeProviderDocuments(): void
    {
        $mutations=[
            'line_original_mismatch'=>static function(array &$s):void{$s['lines'][0]['prices']['originalTotal']['minor']='2621';},
            'line_discount_mismatch'=>static function(array &$s):void{$s['lines'][0]['discounts'][0]['minor']='201';},
            'line_tax_mismatch'=>static function(array &$s):void{$s['fiscalDetails']['lineDefaults']['rate']='20';},
            'order_tax_mismatch'=>static function(array &$s):void{$s['totals']['tax']['minor']='421';},
            'order_discount_mismatch'=>static function(array &$s):void{$s['totals']['discount']['minor']='201';},
            'order_total_mismatch'=>static function(array &$s):void{$s['totals']['current']['minor']='2421';},
            'line_money_invalid'=>static function(array &$s):void{$s['lines'][0]['prices']['originalUnitPrice']['currency']='EUR';},
            'shipping_mapping_pending'=>static function(array &$s):void{$s['totals']['shipping']['minor']='1';},
            'tax_components_unsupported'=>static function(array &$s):void{$s['lines'][0]['taxes'][]=$s['lines'][0]['taxes'][0];},
            'document_fiscal_invalid'=>static function(array &$s):void{$s['source']['reference']=null;},
            'line_fiscal_invalid'=>static function(array &$s):void{$s['lines'][0]['description']=null;},
        ];
        foreach($mutations as $code=>$mutate){$snapshot=F::prepared();$mutate($snapshot);$fiscal=FiscalPreparation::build($snapshot);$report=InvoiceAssembly::check($snapshot,$fiscal);self::assertSame('MISMATCH',$report['status']);self::assertContains($code,array_column($report['issues'],'code'));self::assertFalse($report['readyForProvider']);try{InvoiceAssembly::draft($snapshot,$fiscal,new OperationKey('test-blocked'));self::fail('Mismatch allowed.');}catch(\InvalidArgumentException $error){self::assertSame('Invoice preparation is not reconciled.',$error->getMessage());}}
    }
    public function testLegacyContractRemainsValidAndMixedFiscalDraftsAreRejected(): void
    {
        $legacy=C::invoice();self::assertNull($legacy->details);self::assertSame(10890,$legacy->total->minor);
        $snapshot=F::prepared();$draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot),new OperationKey('test-complete'));
        $this->expectException(\InvalidArgumentException::class);new InvoiceDraft($draft->seller,$draft->customer,$draft->lines,$draft->total,$draft->series,$draft->clientReference);
    }
}
