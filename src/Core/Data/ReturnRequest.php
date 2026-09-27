<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class ReturnRequest
{
    /** @param list<LineAllocation> $lines */
    public function __construct(public ExternalId $orderId, public array $lines, public string $reason)
    {
        if ($lines === [] || trim($reason) === '' || mb_strlen($reason) > 255) { throw new \InvalidArgumentException('Invalid return export.'); }
        $ids = array_map(fn (LineAllocation $line): string => $line->lineId->value,$lines);
        if (count(array_unique($ids)) !== count($ids)) { throw new \InvalidArgumentException('Duplicate return line.'); }
    }
}
