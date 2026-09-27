<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface InvoiceProvider extends CapabilitiesProvider
{
    public function createInvoice(ConnectionContext $context, D\InvoiceDraft $draft, V\OperationKey $key): D\InvoiceSnapshot;
    public function cancelInvoice(ConnectionContext $context, V\ExternalId $invoice, string $reason, V\OperationKey $key): D\ActionResult;
    public function createCreditNote(ConnectionContext $context, V\ExternalId $original, D\InvoiceDraft $credit, string $reason, V\OperationKey $key): D\InvoiceSnapshot;
    public function getInvoice(ConnectionContext $context, V\ExternalId $invoice): D\InvoiceSnapshot;
    public function getPdf(ConnectionContext $context, V\ExternalId $invoice): D\Document;
    public function sendInvoice(ConnectionContext $context, V\ExternalId $invoice, V\EmailAddress $recipient, V\OperationKey $key): D\ActionResult;
}
