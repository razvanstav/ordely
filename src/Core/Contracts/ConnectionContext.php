<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Value\{MerchantId, StoreId, ConnectionId, CorrelationId};
/** Resolved server-side. IDs alone are not authorization; the resolver validates current access. */
final readonly class ConnectionContext
{
    public function __construct(public MerchantId $merchant, public StoreId $store, public ConnectionId $connection, public CorrelationId $correlation) {}
    public function scope(): string { return $this->merchant->value . ':' . $this->store->value . ':' . $this->connection->value; }
}
