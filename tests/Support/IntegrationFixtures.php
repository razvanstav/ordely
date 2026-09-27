<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher,Connections};
use Ordely\Shared\Id;

final class IntegrationFixtures
{
    public static function registry(): ProviderRegistry { return require dirname(__DIR__,2).'/config/providers.php'; }
    public static function cipher(): SecretCipher { return new SecretCipher(new KeyRing('test',['test'=>str_repeat('t',32)])); }
    public static function connection(Sql $db,TenantContext $tenant,string $store,string $provider='fake-carrier'): string
    {
        $service=new Connections($db,self::registry(),self::cipher());$id=$service->create($tenant,Id::new(),$provider,'Test connection',new Secrets(['apiToken'=>'synthetic-test-token']));
        $service->bind($tenant,$id,1,$store,true);return $id;
    }
}
