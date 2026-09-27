<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class OperationKey implements \JsonSerializable
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,128}$/D', $value)) { throw new \InvalidArgumentException('Invalid operation key.'); }
    }
    public function jsonSerialize(): string { return $this->value; }
}
