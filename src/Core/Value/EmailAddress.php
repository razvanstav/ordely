<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class EmailAddress
{
    public string $value;
    public function __construct(string $value)
    {
        $value = trim($value);
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) { throw new \InvalidArgumentException('Invalid email address.'); }
        $this->value = $value;
    }
}
