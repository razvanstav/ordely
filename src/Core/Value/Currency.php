<?php
declare(strict_types=1);
namespace Ordely\Core\Value;

/** Explicit exponent: import adapters must use verified currency metadata, never guess it. */
final readonly class Currency implements \JsonSerializable
{
    public function __construct(public string $code, public int $exponent)
    {
        if (!preg_match('/^[A-Z]{3}$/D', $code) || $exponent < 0 || $exponent > 4) { throw new \InvalidArgumentException('Invalid currency.'); }
    }
    public function same(self $other): bool { return $this->code === $other->code && $this->exponent === $other->exponent; }
    /** @return array{code:string,exponent:int} */
    public function jsonSerialize(): array { return ['code' => $this->code, 'exponent' => $this->exponent]; }
}
