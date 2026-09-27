<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
abstract readonly class EntityId implements \JsonSerializable
{
    final public function __construct(public string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $value)) { throw new \InvalidArgumentException('Invalid entity identifier.'); }
    }
    public static function new(): static { return new static(bin2hex(random_bytes(16))); }
    public function jsonSerialize(): string { return $this->value; }
}
