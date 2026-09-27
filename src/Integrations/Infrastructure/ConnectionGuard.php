<?php
declare(strict_types=1);
namespace Ordely\Integrations\Infrastructure;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Domain\ProviderKind;
use Ordely\Shared\Id;

final readonly class ConnectionGuard
{
    public function __construct(private Sql $db) {}
    /** @return array<string,mixed> */
    public function active(ConnectionContext $context,?ProviderKind $kind=null): array
    {
        $row=$this->db->one("SELECT c.* FROM provider_connections c JOIN store_provider_bindings b ON b.merchant_id=c.merchant_id AND b.connection_id=c.id
            JOIN stores s ON s.merchant_id=b.merchant_id AND s.id=b.store_id JOIN merchants m ON m.id=c.merchant_id
            WHERE c.merchant_id=? AND c.id=? AND b.store_id=? AND c.status='active' AND s.status='active' AND m.status='active'",
            [Id::bytes($context->merchant->value),Id::bytes($context->connection->value),Id::bytes($context->store->value)]);
        if($row===null||($kind!==null&&$row['kind']!==$kind->value)){throw new AccessDenied('connection_unavailable');}
        if(str_starts_with((string)$row['provider_key'],'fake-')&&!in_array(\Ordely\Infrastructure\Configuration\Environment::string('APP_ENV','dev'),['dev','test'],true)){throw new AccessDenied('connection_unavailable');}
        return $row;
    }
}
