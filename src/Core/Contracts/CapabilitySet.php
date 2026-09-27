<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Value\Currency;
final readonly class CapabilitySet
{
    /** @param list<Capability> $features
     * @param list<Currency> $currencies
     * @param list<string> $countries */
    public function __construct(public array $features, public array $currencies = [], public array $countries = []) {}
    public function has(Capability $capability): bool { return in_array($capability, $this->features, true); }
    public function require(Capability $capability): void { if (!$this->has($capability)) { throw new ProviderFailure(ErrorCategory::Unsupported); } }
    public function supportsCurrency(Currency $currency): bool
    {
        foreach ($this->currencies as $supported) { if ($supported->same($currency)) { return true; } }
        return false;
    }
}
