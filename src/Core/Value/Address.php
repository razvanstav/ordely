<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class Address
{
    public function __construct(public string $name, public string $country, public string $city, public string $line, public string $postalCode, public ?string $phone = null)
    {
        foreach ([$name, $city, $line, $postalCode] as $part) { if (trim($part) === '' || mb_strlen($part) > 200 || str_contains($part, "\0")) { throw new \InvalidArgumentException('Invalid address.'); } }
        if (!preg_match('/^[A-Z]{2}$/D', $country) || ($phone !== null && !preg_match('/^\\+?[0-9 ()-]{5,30}$/D', $phone))) { throw new \InvalidArgumentException('Invalid country or phone.'); }
    }
}
