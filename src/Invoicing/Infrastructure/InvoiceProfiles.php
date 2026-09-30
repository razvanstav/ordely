<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use Ordely\Integrations\Infrastructure\{Connections,ConnectionGuard,SecretCipher};
use Ordely\Invoicing\Application\ReadInvoiceConfiguration;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Core\Value\ExternalId;
use Ordely\Shared\Id;

/** A saved company/series selection; not yet a complete seller snapshot or permission to issue. */
final readonly class InvoiceProfiles
{
    public function __construct(private Sql $db,private ProviderRegistry $registry,private SecretCipher $cipher) {}

    public function save(TenantContext $actor,ConnectionContext $context,int $connectionVersion,int $expectedVersion,string $companyId,string $series): int
    {
        if($expectedVersion<0||$connectionVersion<1||$series===''||mb_strlen($series)>255){throw new \InvalidArgumentException('Invalid profile selection.');}
        // All input is checked through the existing discovery service before any fiscal data is persisted.
        $catalog=(new ReadInvoiceConfiguration($this->db,$this->registry,$this->cipher))->read($actor,$context,$connectionVersion,new ExternalId($companyId));
        $name=null;foreach($catalog->companies as $company){if($company['id']===$companyId){$name=$company['name'];break;}}
        if($name===null||$catalog->companyId!==$companyId||!in_array($series,array_column($catalog->series,'name'),true)){throw new \InvalidArgumentException('Unavailable company or series.');}
        $document=new Secrets(['companyId'=>$companyId,'companyName'=>$name,'series'=>$series]);
        return $this->db->transaction(function()use($actor,$context,$connectionVersion,$expectedVersion,$document):int{
            (new Connections($this->db,$this->registry))->manage($actor);
            (new AccessPolicy($this->db))->require($actor,'connections.manage',$context->store->value);
            $scope=[Id::bytes($actor->merchantId),Id::bytes($context->store->value)];
            // Same lock order as connection binding. Serializes creation and rechecks access after the network.
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR UPDATE',$scope);
            $this->db->one('SELECT id FROM provider_connections WHERE merchant_id=? AND id=? FOR SHARE',[$scope[0],Id::bytes($context->connection->value)]);
            $connection=(new ConnectionGuard($this->db))->active($context,ProviderKind::Invoice);
            if((int)$connection['version']!==$connectionVersion){throw new Conflict('connection_changed');}
            $row=$this->db->one('SELECT * FROM invoice_profiles WHERE merchant_id=? AND store_id=? FOR UPDATE',$scope);
            $current=$row===null?0:(int)$row['version'];
            if($current!==$expectedVersion){
                // A lost HTTP response can be retried with the same expected revision and selection.
                if($row!==null&&$current===$expectedVersion+1&&bin2hex((string)$row['connection_id'])===$context->connection->value&&(int)$row['connection_version']===$connectionVersion&&$this->open($actor->merchantId,$context->store->value,$row)->reveal()===$document->reveal()){return $current;}
                throw new Conflict('profile_changed');
            }
            $version=$current+1;
            $envelope=$this->cipher->encrypt($actor->merchantId,$context->store->value,$this->purpose($context->store->value,$context->connection->value,$connectionVersion,$version),$document);
            $this->db->run('INSERT INTO invoice_profiles(merchant_id,store_id,connection_id,connection_version,version,profile_envelope) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE connection_id=VALUES(connection_id),connection_version=VALUES(connection_version),version=VALUES(version),profile_envelope=VALUES(profile_envelope)',[...$scope,Id::bytes($context->connection->value),$connectionVersion,$version,$envelope]);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$context->store->value),Actor::user($actor->userId),AuditAction::InvoiceProfileSaved,$context->store->value,new SafePayload(['store_id'=>$context->store->value,'connection_id'=>$context->connection->value,'version'=>$version]));
            return $version;
        });
    }

    /** @return array<string,mixed>|null */
    public function get(TenantContext $actor,string $store): ?array
    {
        return $this->db->transaction(function()use($actor,$store):?array{
            (new AccessPolicy($this->db))->require($actor,'invoices.read',$store);
            $row=$this->db->one('SELECT * FROM invoice_profiles WHERE merchant_id=? AND store_id=? FOR SHARE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            if($row===null){return null;}
            $connection=bin2hex((string)$row['connection_id']);
            $active=$this->db->one("SELECT c.version FROM provider_connections c JOIN store_provider_bindings b ON b.merchant_id=c.merchant_id AND b.connection_id=c.id WHERE c.merchant_id=? AND c.id=? AND b.store_id=? AND c.status='active' FOR SHARE",[Id::bytes($actor->merchantId),Id::bytes($connection),Id::bytes($store)]);
            $document=$this->open($actor->merchantId,$store,$row)->reveal();
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::InvoiceProfileViewed,$store,new SafePayload(['store_id'=>$store,'version'=>(int)$row['version']]));
            return [...$document,'storeId'=>$store,'connectionId'=>$connection,'connectionVersion'=>(int)$row['connection_version'],'version'=>(int)$row['version'],'needsVerification'=>$active===null||(int)$active['version']!==(int)$row['connection_version']];
        });
    }

    /** @param array<string,mixed> $row */
    private function open(string $merchant,string $store,array $row): Secrets
    {
        return $this->cipher->decrypt($merchant,$store,$this->purpose($store,bin2hex((string)$row['connection_id']),(int)$row['connection_version'],(int)$row['version']),(string)$row['profile_envelope']);
    }
    private function purpose(string $store,string $connection,int $connectionVersion,int $version): string { return 'invoice-profile:'.$store.':'.$connection.':'.$connectionVersion.':'.$version; }
}
