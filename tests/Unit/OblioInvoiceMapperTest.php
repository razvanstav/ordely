<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Oblio\{OblioInvoiceMapper,OblioProvider,OblioTransport};
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Data\InvoiceConfiguration;
use Ordely\Core\Value\{CanonicalJson,OperationKey};
use Ordely\Invoicing\Domain\{FiscalPreparation,InvoiceAssembly};
use Ordely\Tests\Support\{ContractFixtures as C,OblioFixtures as O,PreparationFixtures as F};
use PHPUnit\Framework\TestCase;
final class OblioInvoiceMapperTest extends TestCase
{
    private function configuration(): InvoiceConfiguration {return new InvoiceConfiguration([['id'=>'TEST009','name'=>'Synthetic seller']],'TEST009',[['name'=>'TEST','default'=>false]],[['name'=>'Synthetic rate','percent'=>'21','default'=>false]]);}
    public function testOfflineMappingKeepsMoneyAndExplicitlyDisablesExternalOptions(): void
    {
        $snapshot=F::prepared();$draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot),new OperationKey('business-reference'));$key=new OperationKey('durable-issue-key');
        $body=OblioInvoiceMapper::map($draft,$this->configuration(),$key);self::assertSame('durable-issue-key',$body['idempotencyKey']);self::assertSame('13.10',$body['products'][0]['price']);self::assertSame('21.0000',$body['products'][0]['vatPercentage']);self::assertSame(1,$body['products'][0]['vatIncluded']);self::assertSame('2.00',$body['products'][1]['discount']);self::assertSame(0,$body['products'][1]['discountAllAbove']);
        foreach(['useStock','sendEmail','spvExtern'] as $option){self::assertSame(0,$body[$option]);}self::assertSame(0,$body['products'][0]['save']);self::assertSame(0,$body['client']['save']);self::assertSame(0,$body['client']['autocomplete']);self::assertArrayNotHasKey('email',$body['client']);self::assertArrayNotHasKey('collect',$body);self::assertStringContainsString('"price":"13.10"',CanonicalJson::encode($body));
        $provider=new OblioProvider(O::credentials(),static function():never {throw new \LogicException('Unexpected network.');});
        try{$provider->createInvoice(C::context(),$draft,$key);self::fail('Write enabled prematurely.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Unsupported,$failure->category);}
        $this->expectException(\LogicException::class);OblioTransport::send('POST','/docs/invoice',[],CanonicalJson::encode($body));
    }
    public function testMismatchedNomenclatureAmbiguousRatesAndUnmappedTreatmentAreRejected(): void
    {
        $snapshot=F::prepared();$draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot),new OperationKey('test'));
        $configs=[new InvoiceConfiguration([],'OTHER'),new InvoiceConfiguration($this->configuration()->companies,'TEST009',[]),new InvoiceConfiguration($this->configuration()->companies,'TEST009',$this->configuration()->series,[]),new InvoiceConfiguration($this->configuration()->companies,'TEST009',$this->configuration()->series,[['name'=>'One','percent'=>'21','default'=>false],['name'=>'Two','percent'=>'21.00','default'=>true]])];
        foreach($configs as $config){try{OblioInvoiceMapper::map($draft,$config,new OperationKey('test'));self::fail('Unverified configuration accepted.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Validation,$failure->category);}}
        $snapshot['fiscalDetails']['lineDefaults']=['unit'=>'buc','treatment'=>'exempt','rate'=>'','reason'=>'Synthetic reason'];$snapshot['lines'][0]['taxes']=[];$snapshot['totals']['tax']['minor']='0';
        $draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot),new OperationKey('test-exempt'));
        try{OblioInvoiceMapper::map($draft,$this->configuration(),new OperationKey('test-exempt'));self::fail('Unmapped treatment accepted.');}catch(ProviderFailure $failure){self::assertSame(ErrorCategory::Unsupported,$failure->category);}
    }
}
