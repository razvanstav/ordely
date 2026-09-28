<?php
declare(strict_types=1);
namespace Ordely\Commerce\Infrastructure;

use Ordely\Adapters\Shopify\{AppConfig,GraphqlReader,HttpShopifyGateway,Installations,ShopifyImportSource};
use Ordely\Commerce\Application\ImportService;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};

final class CommerceFactory
{
    public static function cipher(): OrderCipher { return new OrderCipher(new SecretCipher(KeyRing::fromEnvironment())); }
    public static function importer(Sql $db): ImportService
    {
        $config=AppConfig::fromEnvironment();$secret=new SecretCipher(KeyRing::fromEnvironment());
        $installations=new Installations($db,$config,$secret,new HttpShopifyGateway($config));
        return new ImportService($db,new ShopifyImportSource(new GraphqlReader($installations,$config)),new ImportRepository($db,new OrderCipher($secret)));
    }
}
