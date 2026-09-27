<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{ExternalId, Money};
final readonly class RateQuote
{
    public function __construct(public ExternalId $service, public Money $gross, public \DateTimeImmutable $expiresAt)
    {
        if ($gross->minor < 0) { throw new \InvalidArgumentException('Invalid shipping rate.'); }
    }
}
