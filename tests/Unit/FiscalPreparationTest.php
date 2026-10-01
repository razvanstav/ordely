<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Invoicing\Domain\{FiscalPreparation,OrderPreparation};
use Ordely\Tests\Support\PreparationFixtures as F;
use PHPUnit\Framework\TestCase;

final class FiscalPreparationTest extends TestCase
{
    /** @return array<string,mixed> */
    private function snapshot(): array {return OrderPreparation::build(F::order(),['companyId'=>'TEST009','companyName'=>'Synthetic seller','series'=>'TEST','needsVerification'=>false]);}
    public function testExplicitFactsPrepareMappingWithoutIssuingOrChangingMoney(): void
    {
        $snapshot=$this->snapshot();$snapshot['fiscalDetails']=FiscalPreparation::normalize(F::fiscalDetails(),$snapshot);$result=FiscalPreparation::build($snapshot);
        self::assertTrue($result['readyForMapping']);self::assertFalse($result['canIssue']);self::assertSame([],$result['issues']);
        self::assertSame('21.0000',$result['lines'][0]['taxRate']);self::assertSame($snapshot['lines'][0]['prices'],$result['lines'][0]['prices']);
        self::assertSame('SYNTHETIC-PREP-STREET',$result['customer']['address']['street']);self::assertSame('TEST009',$result['seller']['companyId']);
        self::assertFalse(FiscalPreparation::build($snapshot,true)['readyForMapping']);
        $snapshot['issues'][]=['code'=>'order_changed','path'=>'source','message'=>'Changed'];self::assertContains('order_changed',array_column(FiscalPreparation::build($snapshot)['issues'],'code'));
    }
    public function testPartialDataAndLineExceptionsCannotInferTaxOrBorrowWrongRate(): void
    {
        $snapshot=$this->snapshot();$details=F::fiscalDetails();$details['lines']=[['id'=>$snapshot['lines'][0]['id'],'treatment'=>'exempt']];
        $snapshot['fiscalDetails']=FiscalPreparation::normalize($details,$snapshot);$result=FiscalPreparation::build($snapshot);
        self::assertFalse($result['readyForMapping']);self::assertSame('buc',$result['lines'][0]['unit']);self::assertSame('',$result['lines'][0]['taxRate']);
        $details['lines'][0]['reason']='Synthetic reason';$snapshot['fiscalDetails']=FiscalPreparation::normalize($details,$snapshot);self::assertTrue(FiscalPreparation::build($snapshot)['readyForMapping']);
        $details['lines'][0]=['id'=>$snapshot['lines'][0]['id'],'unit'=>'kg'];$snapshot['fiscalDetails']=FiscalPreparation::normalize($details,$snapshot);$result=FiscalPreparation::build($snapshot);
        self::assertSame('kg',$result['lines'][0]['unit']);self::assertSame('21.0000',$result['lines'][0]['taxRate']);
        $snapshot['fiscalDetails']=FiscalPreparation::normalize([],$snapshot);self::assertFalse(FiscalPreparation::build($snapshot)['readyForMapping']);
    }
    public function testInvalidInputRejectsMoneyProfileReplacementDatesRatesAndLineIdentity(): void
    {
        $snapshot=$this->snapshot();
        $invalid=[['totals'=>[]],['seller'=>['companyId'=>'OTHER']],['customer'=>['street'=>'OTHER']],['document'=>['issuedOn'=>'2026-02-30']],['document'=>['issuedOn'=>'2026-10-02','dueOn'=>'2026-10-01']],['lineDefaults'=>['rate'=>21]],['lineDefaults'=>['rate'=>'100.0001']],['lineDefaults'=>['treatment'=>'exempt','rate'=>'0']],['lines'=>[['id'=>'unknown']]],['customer'=>['country'=>'ro']],['customer'=>['taxId'=>"bad\ntext"]]];
        $rejected=0;foreach($invalid as $details){try{FiscalPreparation::normalize($details,$snapshot);self::fail('Invalid fiscal data accepted.');}catch(\InvalidArgumentException){++$rejected;}}self::assertSame(count($invalid),$rejected);
    }
    public function testMissingBillingCanBeCompletedButImportedFactsTakePriorityAfterRefresh(): void
    {
        $order=F::order();$order['billingAddress']=null;$snapshot=OrderPreparation::build($order,['needsVerification'=>false]);$details=F::fiscalDetails();
        $details['customer']+=['name'=>'Synthetic client','street'=>'Manual street','city'=>'Synthetic city','postalCode'=>'000000','country'=>'RO'];
        $snapshot['fiscalDetails']=FiscalPreparation::normalize($details,$snapshot);self::assertTrue(FiscalPreparation::build($snapshot)['readyForMapping']);
        $snapshot['customer']['address']['street']='Imported newer street';self::assertSame('Imported newer street',FiscalPreparation::build($snapshot)['customer']['address']['street']);
    }
}
