<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money};
final readonly class InvoiceSnapshot
{
    public function __construct(public ExternalId $id, public string $number, public Money $total, public bool $creditNote = false, public bool $cancelled = false, public ?ExternalId $originalId = null)
    {
        if ($creditNote !== ($originalId !== null) || trim($number) === '' || $total->minor < 0) { throw new \InvalidArgumentException('Invalid issued document.'); }
    }
}
