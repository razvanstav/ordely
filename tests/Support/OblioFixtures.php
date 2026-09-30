<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Integrations\Domain\Secrets;

final class OblioFixtures
{
    public static function profileRegistry(): \Ordely\Integrations\Application\ProviderRegistry
    {
        return new \Ordely\Integrations\Application\ProviderRegistry(new \Ordely\Integrations\Application\ProviderDefinition('oblio','Oblio',\Ordely\Integrations\Domain\ProviderKind::Invoice,['clientId','clientSecret'],static fn(Secrets $secrets):\Ordely\Adapters\Oblio\OblioProvider=>new \Ordely\Adapters\Oblio\OblioProvider($secrets,static function(string $method,string $path):array {
            if(explode('?',$path)[0]==='/nomenclature/series'){return ['status'=>200,'body'=>'{"status":200,"data":[{"type":"Factura","name":"TEST","default":true},{"type":"Factura","name":"NEXT","default":false}]}','retryAfter'=>null];}
            return self::reply($path);
        }),\Ordely\Adapters\Oblio\OblioProvider::validateCredentials(...)));
    }
    public static function credentials(): Secrets { return new Secrets(['clientId'=>'oblio@example.test','clientSecret'=>'SYNTHETIC-oblio-secret']); }
    /** @return array{status:int,body:string,retryAfter:?int} */
    public static function reply(string $path): array
    {
        $body=match(explode('?',$path)[0]) {
            '/authorize/token'=>'{"access_token":"synthetic-access-token","token_type":"Bearer","expires_in":3600}',
            '/nomenclature/companies'=>'{"status":200,"data":[{"cif":"TEST001","company":"Synthetic company"}]}',
            '/nomenclature/series'=>'{"status":200,"data":[{"type":"Factura","name":"TEST","default":true},{"type":"Proforma","name":"IGNORED","default":false}]}',
            '/nomenclature/vat_rates'=>'{"status":200,"data":[{"name":"Synthetic fractional 7.1250","percent":7.1250,"default":false},{"name":"Synthetic zero","percent":0,"default":true}]}',
            default=>throw new \LogicException('Unexpected transport path.'),
        };return ['status'=>200,'body'=>$body,'retryAfter'=>null];
    }
}
