<?php

declare(strict_types=1);

namespace Ordely\Adapters\Shopify;

final class InvalidIdToken extends \RuntimeException
{
    public function __construct() { parent::__construct('Invalid Shopify identity.'); }
}
