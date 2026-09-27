<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class ExternalId implements \JsonSerializable
{
    public function __construct(public string $value)
    {
        if ($value === '' || strlen($value) > 255 || preg_match('/[\\x00-\\x1f\\x7f]/', $value)) { throw new \InvalidArgumentException('Invalid external reference.'); }
    }
    public function jsonSerialize(): string { return $this->value; }
}
