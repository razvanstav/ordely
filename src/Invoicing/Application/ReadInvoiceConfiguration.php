<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Application;
use Ordely\Core\Contracts\{CommerceConnector,CarrierProvider,InvoiceProvider,InvoiceConfigurationReader,ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Data\InvoiceConfiguration;
use Ordely\Core\Value\ExternalId;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\ProviderKind;
use Ordely\Integrations\Infrastructure\{Connections,ConnectionGuard,ConnectionResolver,SecretCipher};
use Ordely\Operations\Domain\{Actor,AuditAction,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

final readonly class ReadInvoiceConfiguration
{
    public function __construct(private Sql $db,private ProviderRegistry $registry,private SecretCipher $cipher) {}
    public function read(TenantContext $actor,ConnectionContext $context,int $version,?ExternalId $company=null): InvoiceConfiguration
    {
        $this->authorize($actor,$context,$version);
        $configuration=(new ConnectionResolver($this->db,$this->registry,$this->cipher))->withProvider($context,ProviderKind::Invoice,static function(CommerceConnector|CarrierProvider|InvoiceProvider $provider)use($context,$company):InvoiceConfiguration {
            if(!$provider instanceof InvoiceConfigurationReader){throw new ProviderFailure(ErrorCategory::Unsupported);}
            return $provider->readConfiguration($context,$company);
        });
        // No database transaction is held across network calls. Recheck current access before releasing data.
        return $this->db->transaction(function()use($actor,$context,$version,$configuration):InvoiceConfiguration {
            $this->db->one('SELECT id FROM provider_connections WHERE merchant_id=? AND id=? FOR SHARE',[Id::bytes($actor->merchantId),Id::bytes($context->connection->value)]);
            $this->authorize($actor,$context,$version);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$context->store->value),Actor::user($actor->userId),AuditAction::InvoiceConfigurationRead,$context->connection->value,new SafePayload(['version'=>$version,'count'=>count($configuration->companies)+count($configuration->series)+count($configuration->taxRates)]));
            return $configuration;
        });
    }
    private function authorize(TenantContext $actor,ConnectionContext $context,int $version): void
    {
        if($actor->merchantId!==$context->merchant->value){throw new AccessDenied('forbidden');}
        (new Connections($this->db,$this->registry))->manage($actor);
        (new AccessPolicy($this->db))->require($actor,'connections.manage',$context->store->value);
        $current=(new ConnectionGuard($this->db))->active($context,ProviderKind::Invoice);
        if((int)$current['version']!==$version){throw new AccessDenied('connection_changed');}
    }
}
