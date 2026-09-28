<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;
final class AuthorizationLost extends \RuntimeException
{
    public function __construct() { parent::__construct('Shopify authorization must be renewed.'); }
}
