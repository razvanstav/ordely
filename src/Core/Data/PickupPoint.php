<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{Address, ExternalId};
final readonly class PickupPoint
{
    public function __construct(public ExternalId $id, public string $name, public Address $address) {}
}
