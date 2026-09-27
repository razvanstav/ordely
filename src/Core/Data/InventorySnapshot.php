<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class InventorySnapshot
{
    public function __construct(public ExternalId $variantId, public ExternalId $locationId, public int $available, public \DateTimeImmutable $observedAt) {}
}
