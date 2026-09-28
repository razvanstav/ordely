<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Shared\Id;
use Ordely\Tests\Support\InvoiceFixtures as F;
use PHPUnit\Framework\TestCase;

final class InvoiceDraftTest extends TestCase
{
    public function testExactTotalsAndCanonicalDecimalRoundTrip(): void
    {
        $document=F::document();self::assertSame(['net'=>'20.00','tax'=>'3.00','total'=>'23.00','currency'=>'RON'],$document->totals());
        $data=F::data();$data['lines'][0]['unitNet']='0.10';$data['lines'][0]['quantity']=3;$data['lines'][0]['discountNet']='0';$data['lines'][0]['tax']='0.01';
        $small=DraftDocument::fromArray($data);self::assertSame('0.31',$small->totals()['total']);self::assertSame('0.00',$small->data()['lines'][0]['discountNet']);
        self::assertSame($small->totals(),DraftDocument::fromArray($small->data())->totals());
    }
    public function testManyLinesAndZeroTotalRemainDraftCalculations(): void
    {
        $data=F::data();$line=$data['lines'][0];$data['lines']=[];
        for($i=0;$i<30;++$i){$copy=$line;$copy['id']=Id::new();$data['lines'][]=$copy;}
        $draft=DraftDocument::fromArray($data);self::assertCount(30,$draft->lines);self::assertSame('690.00',$draft->totals()['total']);
        $zero=F::data();$zero['lines'][0]['discountNet']='20.20';$zero['lines'][0]['tax']='0.00';self::assertSame('0.00',DraftDocument::fromArray($zero)->totals()['total']);
    }
    public function testFloatPrecisionNegativeDiscountAndOverflowAreRejected(): void
    {
        foreach([['unitNet',0.1],['unitNet','1.001'],['unitNet','-1.00'],['quantity',1.2],['quantity',0],['quantity',1000001],['discountNet','20.21'],['tax','-0.01'],['unitNet','90000000000000.01'],['unitNet','90000000000000.00']] as [$field,$value]){
            $data=F::data();$data['lines'][0][$field]=$value;$this->invalid($data);
        }
    }
    public function testUnknownFieldsInvalidCurrencyAndDuplicateLineIdentityFail(): void
    {
        $cases=[];$data=F::data();$data['total']='0.01';$cases[]=$data;
        $data=F::data();$data['currency']='JPY';$cases[]=$data;
        $data=F::data();$data['lines'][]=$data['lines'][0];$cases[]=$data;
        $data=F::data();$data['customerName']="PRIVATE\0DATA";$cases[]=$data;
        $data=F::data();$data['lines'][0]['taxRate']='21';$cases[]=$data;
        $data=F::data();$data['lines']=[];$cases[]=$data;
        foreach($cases as $case){$this->invalid($case);}
    }
    public function testCipherBindsTenantStoreDraftAndRevisionAndRejectsTampering(): void
    {
        $cipher=F::cipher();$merchant=Id::new();$store=Id::new();$id=Id::new();$sealed=$cipher->seal($merchant,$store,$id,1,F::document());
        self::assertStringNotContainsString('SYNTHETIC-PRIVATE-RECIPIENT',$sealed);self::assertSame(F::document()->data(),$cipher->open($merchant,$store,$id,1,$sealed)->data());
        foreach([[Id::new(),$store,$id,1],[$merchant,Id::new(),$id,1],[$merchant,$store,Id::new(),1],[$merchant,$store,$id,2]] as [$m,$s,$d,$v]){
            try{$cipher->open($m,$s,$d,$v,$sealed);self::fail('Expected bound-context failure.');}catch(\RuntimeException $error){self::assertSame('Invoice draft unavailable.',$error->getMessage());}
        }
        $data=json_decode($sealed,true,flags:JSON_THROW_ON_ERROR);$data['tag']=base64_encode(str_repeat('x',16));
        $this->expectException(\RuntimeException::class);$cipher->open($merchant,$store,$id,1,json_encode($data,JSON_THROW_ON_ERROR));
    }
    /** @param array<string,mixed> $data */
    private function invalid(array $data): void
    {
        try{DraftDocument::fromArray($data);self::fail('Expected invalid draft rejection.');}catch(\InvalidArgumentException $error){self::assertStringNotContainsString('SYNTHETIC-PRIVATE-RECIPIENT',$error->getMessage());}
    }
}
