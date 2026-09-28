<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;
final class ShopifyUnavailable extends \RuntimeException
{
    public function __construct() { parent::__construct('Shopify is temporarily unavailable.'); }
}
