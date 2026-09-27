<?php
declare(strict_types=1);
namespace Ordely\Core\Value;

final readonly class Money implements \JsonSerializable
{
    public const MAX = 9_000_000_000_000_000;
    public function __construct(public int $minor, public Currency $currency)
    {
        if (PHP_INT_SIZE < 8 || $minor < -self::MAX || $minor > self::MAX) { throw new \OverflowException('Money outside supported range.'); }
    }

    public static function decimal(string $amount, Currency $currency): self
    {
        $fraction = $currency->exponent === 0 ? '' : '(?:\\.([0-9]{1,' . $currency->exponent . '}))?';
        if (!preg_match('/^(-?)(0|[1-9][0-9]*)' . $fraction . '$/D', $amount, $parts)) { throw new \InvalidArgumentException('Invalid decimal money.'); }
        $digits = ltrim($parts[2] . str_pad($parts[3] ?? '', $currency->exponent, '0'), '0');
        $maximum = (string) self::MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) { throw new \OverflowException('Money outside supported range.'); }
        return new self(($parts[1] === '-' ? -1 : 1) * (int) $digits, $currency);
    }

    public function format(): string
    {
        $digits = str_pad((string) abs($this->minor), $this->currency->exponent + 1, '0', STR_PAD_LEFT);
        $value = $this->currency->exponent === 0 ? $digits : substr($digits, 0, -$this->currency->exponent) . '.' . substr($digits, -$this->currency->exponent);
        return ($this->minor < 0 ? '-' : '') . $value;
    }

    public function plus(self $other): self { $this->assertCurrency($other); return new self($this->minor + $other->minor, $this->currency); }
    public function minus(self $other): self { $this->assertCurrency($other); return new self($this->minor - $other->minor, $this->currency); }

    public function times(int $quantity): self
    {
        if ($quantity < 0 || $quantity > 1_000_000) { throw new \InvalidArgumentException('Invalid multiplier.'); }
        if ($quantity > 0 && abs($this->minor) > intdiv(self::MAX, $quantity)) { throw new \OverflowException('Money multiplication overflow.'); }
        return new self($this->minor * $quantity, $this->currency);
    }

    /** Round half away from zero. Avoid overflowing amount*numerator before division. */
    public function ratio(int $numerator, int $denominator): self
    {
        if ($numerator < 0 || $numerator > 1_000_000 || $denominator < 1 || $denominator > 1_000_000) { throw new \InvalidArgumentException('Invalid ratio.'); }
        $amount = abs($this->minor); $whole = intdiv($amount, $denominator);
        if ($numerator > 0 && $whole > intdiv(self::MAX, $numerator)) { throw new \OverflowException('Ratio overflow.'); }
        $remainder = ($amount % $denominator) * $numerator;
        $rounded = $whole * $numerator + intdiv($remainder, $denominator) + ((($remainder % $denominator) * 2 >= $denominator) ? 1 : 0);
        return new self(($this->minor < 0 ? -1 : 1) * $rounded, $this->currency);
    }

    /** Largest remainder; ties retain input order.
     * @param array<int,int> $weights
     * @return list<self> */
    public function allocate(array $weights): array
    {
        if ($weights === [] || !array_is_list($weights) || count($weights) > 1000) { throw new \InvalidArgumentException('Invalid allocation.'); }
        $total = 0;
        foreach ($weights as $weight) {
            if ($weight < 0 || $weight > 1_000_000 || $total + $weight > 1_000_000) { throw new \InvalidArgumentException('Invalid allocation weight.'); }
            $total += $weight;
        }
        if ($total === 0) { throw new \InvalidArgumentException('Zero allocation total.'); }
        $amount = abs($this->minor); $allocated = []; $remainders = []; $used = 0;
        foreach ($weights as $index => $weight) {
            $remainder = ($amount % $total) * $weight;
            $allocated[$index] = intdiv($amount, $total) * $weight + intdiv($remainder, $total);
            $remainders[$index] = $remainder % $total; $used += $allocated[$index];
        }
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $index) { if ($used >= $amount) { break; } ++$allocated[$index]; ++$used; }
        return array_map(fn (int $value): self => new self(($this->minor < 0 ? -1 : 1) * $value, $this->currency), array_values($allocated));
    }

    public function assertCurrency(self $other): void
    {
        if (!$this->currency->same($other->currency)) { throw new \InvalidArgumentException('Currency mismatch.'); }
    }
    /** Minor units are a JSON string: JavaScript cannot represent every supported amount exactly.
     * @return array{minor:string,currency:Currency} */
    public function jsonSerialize(): array { return ['minor' => (string) $this->minor, 'currency' => $this->currency]; }
}
