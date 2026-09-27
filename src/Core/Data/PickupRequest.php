<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{Address, ExternalId};
final readonly class PickupRequest
{
    /** @param list<ExternalId> $shipments */
    public function __construct(public Address $address, public array $shipments, public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
        if ($shipments === [] || $from >= $to) { throw new \InvalidArgumentException('Invalid pickup window.'); }
    }
}
