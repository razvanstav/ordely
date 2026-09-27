<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Contracts\CapabilitySet;
use Ordely\Core\Value\ExternalId;
final readonly class ServiceOption
{
    public function __construct(public ExternalId $id, public string $name, public CapabilitySet $capabilities) {}
}
