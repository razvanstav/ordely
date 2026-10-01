<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Data\InvoiceDraft;
use Ordely\Core\Value\{CanonicalJson,ConnectionId,CorrelationId,ExternalId,MerchantId,OperationKey,StoreId};
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Domain\ProviderKind;
use Ordely\Integrations\Infrastructure\ConnectionGuard;
use Ordely\Invoicing\Domain\{FiscalPreparation,InvoiceAssembly};
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,OperationResult,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{AuditLog,ExternalOperations};
use Ordely\Shared\Id;

/** Immutable initial invoice intent. No provider or worker is wired by this repository. */
final readonly class IssueIntents
{
    public function __construct(private Sql $db,private OrderDrafts $drafts,private IssueCipher $cipher) {}
    /** @return array<string,mixed> */
    public function prepare(TenantContext $actor,string $store,string $order,int $expectedVersion): array
    {
        if($expectedVersion<1||$expectedVersion>4294967295){throw new \InvalidArgumentException('Invalid draft version.');}
        return $this->db->transaction(function()use($actor,$store,$order,$expectedVersion):array{
            (new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);
            $scope=[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($order)];
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR UPDATE',array_slice($scope,0,2));
            $existing=$this->db->one('SELECT * FROM invoice_issue_intents WHERE merchant_id=? AND store_id=? AND order_id=? FOR UPDATE',$scope);
            if($existing!==null){if((int)$existing['draft_version']!==$expectedVersion){throw new Conflict('invoice_intent_exists');}return $this->metadata($existing);}
            $draft=$this->drafts->get($actor,$store,$order);
            if($draft===null||(int)$draft['version']!==$expectedVersion||$draft['sourceChanged']){throw new Conflict('invoice_draft_changed');}
            $snapshot=$draft['snapshot'];$seller=$snapshot['seller'];$id=Id::new();
            // Refuse incomplete or unreconciled snapshots before persisting the intent.
            InvoiceAssembly::draft($snapshot,$draft['fiscal'],$this->businessKey($id));
            $profile=$this->db->one('SELECT connection_id,version,connection_version FROM invoice_profiles WHERE merchant_id=? AND store_id=? FOR SHARE',array_slice($scope,0,2));
            if($profile===null||(int)$profile['version']!==$seller['version']||(int)$profile['connection_version']!==$seller['connectionVersion']){throw new Conflict('invoice_profile_changed');}
            $row=['id'=>Id::bytes($id),'merchant_id'=>$scope[0],'store_id'=>$scope[1],'order_id'=>$scope[2],'draft_version'=>$expectedVersion,'connection_id'=>$profile['connection_id'],'connection_version'=>(int)$profile['connection_version'],'profile_version'=>(int)$profile['version'],'correlation_id'=>Id::bytes(Id::new())];
            $this->guardConnection($row);
            $envelope=$this->cipher->seal($row,$snapshot);$hash=hash('sha256',CanonicalJson::encode($snapshot),true);
            $this->db->run('INSERT INTO invoice_issue_intents(id,merchant_id,store_id,order_id,draft_version,connection_id,connection_version,profile_version,snapshot_envelope,snapshot_hash,correlation_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$row['id'],...$scope,$expectedVersion,$row['connection_id'],$row['connection_version'],$row['profile_version'],$envelope,$hash,$row['correlation_id']]);
            $this->audit($actor,$store,$id,AuditAction::InvoiceIssuePrepared,$expectedVersion);
            return $this->metadata($this->row($actor,$store,$id));
        });
    }
    /** Reading a result does not require the CMS source to still exist.
     * @return array<string,mixed> */
    public function get(TenantContext $actor,string $store,string $id): array
    {
        return $this->db->transaction(function()use($actor,$store,$id):array{
            (new AccessPolicy($this->db))->require($actor,'invoices.read',$store);$row=$this->row($actor,$store,$id);
            $this->audit($actor,$store,$id,AuditAction::InvoiceIssueViewed,(int)$row['draft_version']);return $this->metadata($row);
        });
    }
    /** Internal execution port only; future adapter must verify its current catalog before any write.
     * @param \Closure(ConnectionContext,InvoiceDraft,OperationKey):ExternalId $call */
    public function execute(TenantContext $actor,string $store,string $id,\Closure $call): OperationResult
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Invoice execution requires a committed intent.');}
        $row=$this->db->transaction(function()use($actor,$store,$id):array{(new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);return $this->row($actor,$store,$id);});
        $context=$this->connection($row);
        $operations=new ExternalOperations($this->db,function(ConnectionContext $current)use($actor,$row):void{
            if($current->merchant->value!==$actor->merchantId||$current->store->value!==bin2hex($row['store_id'])||$current->connection->value!==bin2hex($row['connection_id'])){throw new AccessDenied('invoice_context_changed');}
            $this->validate($actor,$row);
        });
        // Only safe references enter Operations; the full payload stays encrypted in Invoicing.
        return $operations->execute($context,'invoice.issue',$this->businessKey($id),new SafePayload(['invoice_id'=>$id,'version'=>(int)$row['draft_version'],'evidence_sha256'=>bin2hex($row['snapshot_hash'])]),function(OperationKey $key)use($actor,$row,$call,$context):ExternalId{
            $snapshot=$this->validate($actor,$row);
            return $call($context,InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot,false),$key),$key);
        });
    }
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function validate(TenantContext $actor,array $row): array
    {
        return $this->db->transaction(function()use($actor,$row):array{
            $store=bin2hex($row['store_id']);(new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR SHARE',[$row['merchant_id'],$row['store_id']]);
            $this->guardConnection($row);$snapshot=$this->cipher->open($row);
            if(!hash_equals($row['snapshot_hash'],hash('sha256',CanonicalJson::encode($snapshot),true))){throw new \RuntimeException('Invoice issue snapshot unavailable.');}
            $current=$this->drafts->get($actor,$store,bin2hex($row['order_id']));
            if($current===null||$current['sourceChanged']||(int)$current['version']!==(int)$row['draft_version']||!hash_equals($row['snapshot_hash'],hash('sha256',CanonicalJson::encode($current['snapshot']),true))){throw new Conflict('invoice_snapshot_changed');}
            InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot,false),$this->businessKey(bin2hex($row['id'])));return $snapshot;
        });
    }
    /** @param array<string,mixed> $row */
    private function guardConnection(array $row): void
    {
        $this->db->one('SELECT id FROM provider_connections WHERE merchant_id=? AND id=? FOR SHARE',[$row['merchant_id'],$row['connection_id']]);
        $connection=(new ConnectionGuard($this->db))->active($this->connection($row),ProviderKind::Invoice);
        if((int)$connection['version']!==(int)$row['connection_version']){throw new Conflict('invoice_connection_changed');}
    }
    /** @param array<string,mixed> $row */
    private function connection(array $row): ConnectionContext {return new ConnectionContext(new MerchantId(bin2hex($row['merchant_id'])),new StoreId(bin2hex($row['store_id'])),new ConnectionId(bin2hex($row['connection_id'])),new CorrelationId(bin2hex($row['correlation_id'])));}
    private function businessKey(string $id): OperationKey {return new OperationKey('invoice-initial:'.$id);}
    /** @return array<string,mixed> */
    private function row(TenantContext $actor,string $store,string $id): array {return $this->db->one('SELECT * FROM invoice_issue_intents WHERE merchant_id=? AND store_id=? AND id=?',[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)])??throw new AccessDenied('invoice_intent_unavailable');}
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function metadata(array $row): array
    {
        $operation=$this->db->one("SELECT id,status,fencing_version,attempt_count,provider_reference FROM external_operations WHERE merchant_id=? AND store_id=? AND type='invoice.issue' AND business_key=?",[$row['merchant_id'],$row['store_id'],hash('sha256',$this->businessKey(bin2hex($row['id']))->value,true)]);
        return ['id'=>bin2hex($row['id']),'storeId'=>bin2hex($row['store_id']),'orderId'=>bin2hex($row['order_id']),'draftVersion'=>(int)$row['draft_version'],'status'=>$operation['status']??'PREPARED','operationId'=>$operation===null?null:bin2hex($operation['id']),'operationVersion'=>$operation===null?0:(int)$operation['fencing_version'],'attempts'=>$operation===null?0:(int)$operation['attempt_count'],'providerReference'=>$operation['provider_reference']??null,'executionEnabled'=>false,'createdAt'=>$row['created_at']];
    }
    private function audit(TenantContext $actor,string $store,string $id,AuditAction $action,int $version): void {(new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),$action,$id,new SafePayload(['invoice_id'=>$id,'version'=>$version]));}
}
