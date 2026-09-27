<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 1_000_000) { throw new \InvalidArgumentException('Quantity must be a positive V1 integer.'); }
    }
}
