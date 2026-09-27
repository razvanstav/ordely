<?php
declare(strict_types=1);
namespace Ordely\Integrations\Infrastructure;
use Ordely\Core\Contracts\{ConnectionContext,CommerceConnector,CarrierProvider,InvoiceProvider};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\ProviderKind;

final readonly class ConnectionResolver
{
    public function __construct(private Sql $db,private ProviderRegistry $registry,private SecretCipher $cipher) {}
    /** Resolve immediately before use, never cache an authenticated adapter in a job.
     * @template T
     * @param \Closure(CommerceConnector|CarrierProvider|InvoiceProvider):T $call
     * @return T */
    public function withProvider(ConnectionContext $context,ProviderKind $kind,\Closure $call): mixed
    {
        $row=(new ConnectionGuard($this->db))->active($context,$kind);
        $definition=$this->registry->get((string)$row['provider_key']);
        if($definition->kind!==$kind){throw new \LogicException('Incorrect provider kind.');}
        $secret=$this->cipher->decrypt($context->merchant->value,$context->connection->value,$definition->key,(string)$row['credentials_envelope']);
        $provider=$definition->build($secret);
        // Revalidate after potentially slow key/factory work as well.
        $current=(new ConnectionGuard($this->db))->active($context,$kind);
        if((int)$current['version']!==(int)$row['version']){throw new \Ordely\Identity\Domain\AccessDenied('connection_changed');}
        return $call($provider);
    }
}
