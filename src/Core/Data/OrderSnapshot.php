<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money};
final readonly class OrderSnapshot
{
    /** @param list<CommercialLine> $lines */
    public function __construct(public ExternalId $id, public string $number, public array $lines, public CustomerSnapshot $customer, public Money $shippingGross, public Money $total, public Money $paid, public \DateTimeImmutable $updatedAt, public string $note = '')
    {
        if ($lines === [] || trim($number) === '' || $shippingGross->minor < 0 || $paid->minor < 0 || mb_strlen($note) > 2000) { throw new \InvalidArgumentException('Invalid order snapshot.'); }
        $sum = $shippingGross; $ids = [];
        foreach ($lines as $line) { $sum = $sum->plus($line->total); $ids[] = $line->id->value; }
        $sum->assertCurrency($total); $sum->assertCurrency($paid);
        if ($sum->minor !== $total->minor || count(array_unique($ids)) !== count($ids)) { throw new \InvalidArgumentException('Order totals or line IDs mismatch.'); }
    }
}
