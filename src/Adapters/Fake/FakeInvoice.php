<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Contracts\{Capability,CapabilitySet,ConnectionContext,ErrorCategory,InvoiceProvider,ProviderFailure};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;

final class FakeInvoice implements InvoiceProvider
{
    /** @var array<string,array<string,D\InvoiceSnapshot>> */
    private array $invoices=[];
    /** @var array<string,array<string,V\EmailAddress>> */
    private array $deliveries=[];
    private readonly CapabilitySet $features;
    public function __construct(public readonly Effects $effects=new Effects(),?CapabilitySet $features=null)
    {
        $this->features=$features ?? new CapabilitySet([Capability::Invoice,Capability::CancelInvoice,Capability::CreditNote,Capability::Pdf,Capability::SendInvoice,Capability::NativeIdempotency],[new V\Currency('RON',2)],['RO']);
    }
    public function capabilities(ConnectionContext $context): CapabilitySet { return $this->features; }
    /** @return array<string,V\EmailAddress> */
    public function inspectDeliveries(ConnectionContext $context): array { return $this->deliveries[$context->scope()] ?? []; }
    public function createInvoice(ConnectionContext $context,D\InvoiceDraft $draft,V\OperationKey $key): D\InvoiceSnapshot
    {
        $this->features->require(Capability::Invoice);$this->validate($draft);
        return $this->effects->once($context,'invoice.create',$key,$draft,D\InvoiceSnapshot::class,fn():D\InvoiceSnapshot=>$this->issue($context,$draft));
    }
    private function issue(ConnectionContext $context,D\InvoiceDraft $draft,?V\ExternalId $original=null): D\InvoiceSnapshot
    {
        $id=new V\ExternalId('test-invoice-'.bin2hex(random_bytes(8)));
        $number='TEST-'.(count($this->invoices[$context->scope()] ?? [])+1);
        return $this->invoices[$context->scope()][$id->value]=new D\InvoiceSnapshot($id,$number,$draft->total,$original!==null,false,$original);
    }
    private function validate(D\InvoiceDraft $draft): void
    {
        if(!$this->features->supportsCurrency($draft->total->currency) || !in_array($draft->seller->address->country,$this->features->countries,true)){throw new ProviderFailure(ErrorCategory::Unsupported);}
    }
    public function getInvoice(ConnectionContext $context,V\ExternalId $invoice): D\InvoiceSnapshot
    {
        return $this->invoices[$context->scope()][$invoice->value] ?? throw new ProviderFailure(ErrorCategory::NotFound);
    }
    public function cancelInvoice(ConnectionContext $context,V\ExternalId $invoice,string $reason,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::CancelInvoice);$this->reason($reason);
        return $this->effects->once($context,'invoice.cancel',$key,[$invoice,$reason],D\ActionResult::class,function()use($context,$invoice):D\ActionResult{
            $old=$this->getInvoice($context,$invoice);
            foreach($this->invoices[$context->scope()] as $credit){if($credit->originalId?->value===$invoice->value && !$credit->cancelled){throw new ProviderFailure(ErrorCategory::Validation);}}
            $this->invoices[$context->scope()][$invoice->value]=new D\InvoiceSnapshot($old->id,$old->number,$old->total,$old->creditNote,true,$old->originalId);
            return new D\ActionResult($invoice,true);
        });
    }
    public function createCreditNote(ConnectionContext $context,V\ExternalId $original,D\InvoiceDraft $credit,string $reason,V\OperationKey $key): D\InvoiceSnapshot
    {
        $this->features->require(Capability::CreditNote);$this->validate($credit);$this->reason($reason);
        return $this->effects->once($context,'invoice.credit',$key,[$original,$credit,$reason],D\InvoiceSnapshot::class,function()use($context,$original,$credit):D\InvoiceSnapshot{
            $invoice=$this->getInvoice($context,$original);$invoice->total->assertCurrency($credit->total);$remaining=$invoice->total;
            foreach($this->invoices[$context->scope()] as $issued){if($issued->originalId?->value===$original->value && !$issued->cancelled){$remaining=$remaining->minus($issued->total);}}
            if($invoice->cancelled || $invoice->creditNote || $credit->total->minor>$remaining->minor){throw new ProviderFailure(ErrorCategory::Validation);}
            return $this->issue($context,$credit,$original);
        });
    }
    public function getPdf(ConnectionContext $context,V\ExternalId $invoice): D\Document
    {
        $this->features->require(Capability::Pdf);$this->getInvoice($context,$invoice);return Documents::pdf();
    }
    public function sendInvoice(ConnectionContext $context,V\ExternalId $invoice,V\EmailAddress $recipient,V\OperationKey $key): D\ActionResult
    {
        $this->features->require(Capability::SendInvoice);
        return $this->effects->once($context,'invoice.send',$key,[$invoice,$recipient],D\ActionResult::class,function()use($context,$invoice,$recipient):D\ActionResult{
            $this->getInvoice($context,$invoice);$this->deliveries[$context->scope()][$invoice->value]=$recipient;return new D\ActionResult($invoice,true);
        });
    }
    private function reason(string $reason): void { if(trim($reason)==='' || mb_strlen($reason)>255){throw new ProviderFailure(ErrorCategory::Validation);} }
}
