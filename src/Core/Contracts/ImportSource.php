<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;

use Ordely\Core\Data\ImportPage;

/** Read-only source facts. Kept separate from validated invoice/fulfillment commands. */
interface ImportSource
{
    public function page(ConnectionContext $context, string $collection, ?string $parent, ?string $cursor, ?string $since): ImportPage;
}
