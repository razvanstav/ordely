<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Oblio\OblioInvoiceResponse as Mapper;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Data\InvoiceDraft;
use Ordely\Core\Value\OperationKey;
use Ordely\Invoicing\Domain\{FiscalPreparation,InvoiceAssembly};
use Ordely\Tests\Support\PreparationFixtures as F;
use PHPUnit\Framework\TestCase;

final class OblioInvoiceResponseTest extends TestCase
{
    private function draft(): InvoiceDraft {$snapshot=F::prepared();return InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot,false),new OperationKey('response-test'));}
    /** @return array<string,mixed> */
    private function row(): array {return ['id'=>'900001','seriesName'=>'TEST','number'=>'0001','type'=>'Factura','draft'=>'0','canceled'=>'0','currency'=>'RON','precision'=>'2','total'=>'24.2000','useStock'=>'0','issueDate'=>'2026-10-01','dueDate'=>'2026-10-02','link'=>'https://example.test/DO-NOT-PERSIST-LINK'];}
    private function receipt(): string {return '{"status":200,"data":{"seriesName":"TEST","number":"0001","link":"https://example.test/DO-NOT-PERSIST-LINK"}}';}
    public function testReceiptPreservesNumberAndOnlyVerifiedListTotalBecomesSnapshot(): void
    {
        $draft=$this->draft();self::assertSame(['series'=>'TEST','number'=>'0001'],Mapper::receipt($this->receipt(),$draft));$result=Mapper::confirmed($this->receipt(),json_encode(['status'=>200,'data'=>[$this->row()]],JSON_THROW_ON_ERROR),$draft);self::assertSame('oblio:900001',$result->id->value);self::assertSame('TEST 0001',$result->number);self::assertSame(2420,$result->total->minor);self::assertFalse($result->creditNote);self::assertStringNotContainsString('http',$result->id->value);
        $this->expectException(ProviderFailure::class);Mapper::confirmed($this->receipt(),$this->receipt(),$draft);
    }
    public function testInvalidAmbiguousOrDifferentResultIsAlwaysUnknown(): void
    {
        $draft=$this->draft();foreach(['total'=>'24.2100','precision'=>'4','currency'=>'EUR','draft'=>'1','canceled'=>'1','type'=>'Proforma','useStock'=>'1','issueDate'=>'2026-10-02','dueDate'=>'2026-10-03','number'=>'1','id'=>null] as $field=>$value){$row=$this->row();$row[$field]=$value;$this->unknown($draft,json_encode(['status'=>200,'data'=>[$row]],JSON_THROW_ON_ERROR));}
        foreach(['24.2001','2.42e1','24.2010',24.2] as $amount){$row=$this->row();$row['total']=$amount;$this->unknown($draft,json_encode(['status'=>200,'data'=>[$row]],JSON_THROW_ON_ERROR));}
        foreach(['bad-json','[]',json_encode(['status'=>200,'data'=>[]],JSON_THROW_ON_ERROR),json_encode(['status'=>200,'data'=>[$this->row(),$this->row()]],JSON_THROW_ON_ERROR)] as $body){$this->unknown($draft,$body);}
        foreach(['{"status":200,"data":{"seriesName":"FOREIGN","number":"0001"}}','{"status":200,"data":{"seriesName":"TEST","number":1}}'] as $receipt){try{Mapper::receipt($receipt,$draft);self::fail('Invalid receipt');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Unknown,$error->category);}}
    }
    private function unknown(InvoiceDraft $draft,string $body): void {try{Mapper::confirmed($this->receipt(),$body,$draft);self::fail('Invalid result');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Unknown,$error->category);}}
}
