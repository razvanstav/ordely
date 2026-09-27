<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface CapabilitiesProvider
{
    public function capabilities(ConnectionContext $context): CapabilitySet;
}
