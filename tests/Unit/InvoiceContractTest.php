<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Adapters\Fake\FakeInvoice;
use Ordely\Core\Contracts\{Capability,CapabilitySet,ConnectionContext,ErrorCategory,InvoiceProvider,ProviderFailure};
use Ordely\Core\Value as V;
use Ordely\Tests\Support\ContractFixtures as F;
use PHPUnit\Framework\TestCase;

final class InvoiceContractTest extends TestCase
{
    private function adapter(): InvoiceProvider { return new FakeInvoice(); }
    public function testInvoiceCreditAndCancellationAreSeparateIdempotentEffects(): void
    {
        $adapter=$this->adapter();$ctx=F::context();$draft=F::invoice();$key=new V\OperationKey('invoice-1');
        $invoice=$adapter->createInvoice($ctx,$draft,$key);self::assertSame($invoice,$adapter->createInvoice($ctx,$draft,$key));
        self::assertSame(10890,$adapter->getInvoice($ctx,$invoice->id)->total->minor);
        self::assertFalse($invoice->creditNote);self::assertStringStartsWith('TEST-',$invoice->number);
        $pdf=$adapter->getPdf($ctx,$invoice->id);self::assertSame('application/pdf',$pdf->mime);self::assertStringContainsString('NOT VALID',$pdf->contents());
        self::assertStringNotContainsString('%PDF',json_encode($pdf,JSON_THROW_ON_ERROR));
        self::assertTrue($adapter->sendInvoice($ctx,$invoice->id,new V\EmailAddress('test@example.test'),new V\OperationKey('send-1'))->accepted);
        $credit=$adapter->createCreditNote($ctx,$invoice->id,$draft,'Test correction',new V\OperationKey('credit-1'));
        self::assertTrue($credit->creditNote);self::assertSame($invoice->id->value,$credit->originalId?->value);
        self::assertFalse($adapter->getInvoice($ctx,$invoice->id)->cancelled);
        self::assertTrue($adapter->cancelInvoice($ctx,$credit->id,'Test cancellation',new V\OperationKey('cancel-credit'))->accepted);
        self::assertTrue($adapter->cancelInvoice($ctx,$invoice->id,'Test cancellation',new V\OperationKey('cancel-original'))->accepted);
        self::assertTrue($adapter->getInvoice($ctx,$invoice->id)->cancelled);
    }
    public function testCrossTenantStoreAndConnectionCannotReadDocumentOrCreditIt(): void
    {
        $adapter=$this->adapter();$ctx=F::context();$invoice=$adapter->createInvoice($ctx,F::invoice(),new V\OperationKey('invoice-1'));
        foreach([F::context(),new ConnectionContext($ctx->merchant,V\StoreId::new(),$ctx->connection,V\CorrelationId::new()),new ConnectionContext($ctx->merchant,$ctx->store,V\ConnectionId::new(),V\CorrelationId::new())] as $other){
            try{$adapter->getPdf($other,$invoice->id);self::fail('Expected scope rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::NotFound,$error->category);}
            try{$adapter->createCreditNote($other,$invoice->id,F::invoice(),'Test',new V\OperationKey('credit'));self::fail('Expected scope rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::NotFound,$error->category);}
        }
    }
    public function testExcessCreditsAndUnsupportedActionsAreRejected(): void
    {
        $ctx=F::context();$adapter=$this->adapter();$invoice=$adapter->createInvoice($ctx,F::invoice(),new V\OperationKey('original'));
        $adapter->createCreditNote($ctx,$invoice->id,F::invoice(),'Test',new V\OperationKey('credit-1'));
        try{$adapter->createCreditNote($ctx,$invoice->id,F::invoice(),'Test',new V\OperationKey('credit-2'));self::fail('Expected over-credit rejection.');}catch(ProviderFailure $error){self::assertSame(ErrorCategory::Validation,$error->category);}
        $limited=new FakeInvoice(features:new CapabilitySet([Capability::Invoice],[new V\Currency('RON',2)],['RO']));
        self::assertFalse($limited->capabilities($ctx)->has(Capability::CreditNote));
        $this->expectException(ProviderFailure::class);$limited->createCreditNote($ctx,$invoice->id,F::invoice(),'Test',new V\OperationKey('denied'));
    }
    public function testFakeDeliveryHasNoNetworkSideEffectAndCanBeInspected(): void
    {
        $adapter=new FakeInvoice();$ctx=F::context();$invoice=$adapter->createInvoice($ctx,F::invoice(),new V\OperationKey('invoice'));
        $adapter->sendInvoice($ctx,$invoice->id,new V\EmailAddress('test@example.test'),new V\OperationKey('send'));
        self::assertSame('test@example.test',$adapter->inspectDeliveries($ctx)[$invoice->id->value]->value);
        self::assertSame([],$adapter->inspectDeliveries(F::context()));
    }
}
