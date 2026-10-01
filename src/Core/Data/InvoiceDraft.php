<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money, OperationKey};
final readonly class InvoiceDraft
{
    /** @param list<CommercialLine>|list<FiscalLine> $lines */
    public function __construct(public CompanyDetails $seller, public CustomerSnapshot|FiscalCustomer $customer, public array $lines, public Money $total, public ExternalId $series, public OperationKey $clientReference,public ?InvoiceDetails $details=null)
    {
        if ($lines === [] || $total->minor < 0) { throw new \InvalidArgumentException('Invoice needs lines.'); }
        $sum = new Money(0,$total->currency); $ids = [];
        foreach ($lines as $line) {
            if($details!==null&&(!$line instanceof FiscalLine||!$customer instanceof FiscalCustomer||$line->basis!==$details->basis||($details->sellerVatStatus==='not_registered'&&$line->tax->minor!==0))){throw new \InvalidArgumentException('Inconsistent fiscal draft.');}
            if($details===null&&($line instanceof FiscalLine||$customer instanceof FiscalCustomer)){throw new \InvalidArgumentException('Fiscal details required.');}
            $sum = $sum->plus($line->total); $ids[] = $line->id->value;
        }
        if ($sum->minor !== $total->minor || count(array_unique($ids)) !== count($ids)) { throw new \InvalidArgumentException('Invoice total or line IDs mismatch.'); }
    }
}
