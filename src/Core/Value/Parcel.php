<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class Parcel
{
    public function __construct(public int $weightGrams, public int $lengthMm, public int $widthMm, public int $heightMm)
    {
        foreach ([$weightGrams, $lengthMm, $widthMm, $heightMm] as $value) { if ($value < 1 || $value > 1_000_000) { throw new \InvalidArgumentException('Invalid parcel units.'); } }
    }
}
