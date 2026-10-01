<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Data\{InvoiceDraft,InvoiceSnapshot};
use Ordely\Core\Value\{CanonicalJson,ConnectionId,CorrelationId,ExternalId,MerchantId,OperationKey,StoreId};
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Domain\ProviderKind;
use Ordely\Integrations\Infrastructure\ConnectionGuard;
use Ordely\Invoicing\Domain\{FiscalPreparation,InvoiceAssembly};
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,ExternalLease,OperationResult,SafePayload,Scope};
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
            $existing=$this->db->one("SELECT * FROM invoice_issue_intents WHERE merchant_id=? AND store_id=? AND order_id=? AND lifecycle='ACTIVE' FOR UPDATE",$scope);
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
    /** Cancellation affects only an unreserved local intent, never a provider document.
     * @return array<string,mixed> */
    public function cancel(TenantContext $actor,string $store,string $id,int $expectedVersion): array
    {
        if($expectedVersion<1||$expectedVersion>=4294967295){throw new \InvalidArgumentException('Invalid intent version.');}
        return $this->db->transaction(function()use($actor,$store,$id,$expectedVersion):array{
            (new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $row=$this->row($actor,$store,$id,' FOR UPDATE');
            if($row['lifecycle']==='CANCELLED'&&(int)$row['version']===$expectedVersion+1){return $this->metadata($row);}
            if($row['lifecycle']!=='ACTIVE'||(int)$row['version']!==$expectedVersion){throw new Conflict('invoice_intent_changed');}
            if($this->operation($row,' FOR UPDATE')!==null){throw new Conflict('invoice_already_reserved');}
            $this->db->run("UPDATE invoice_issue_intents SET lifecycle='CANCELLED',version=version+1,cancelled_at=UTC_TIMESTAMP(6) WHERE merchant_id=? AND store_id=? AND id=?",[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)]);
            $this->audit($actor,$store,$id,AuditAction::InvoiceIssueCancelled,$expectedVersion+1);return $this->metadata($this->row($actor,$store,$id));
        });
    }
    /** Internal execution port only; future adapter must verify its current catalog before any write.
     * @param \Closure(ConnectionContext,InvoiceDraft,OperationKey):InvoiceSnapshot $call */
    public function execute(TenantContext $actor,string $store,string $id,\Closure $call): OperationResult
    {
        if($this->db->pdo->inTransaction()){throw new \LogicException('Invoice execution requires a committed intent.');}
        $row=$this->db->transaction(function()use($actor,$store,$id):array{(new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);return $this->row($actor,$store,$id);});
        $context=$this->connection($row);$result=new class {public ?InvoiceSnapshot $document=null;};
        $operations=new ExternalOperations($this->db,function(ConnectionContext $current)use($actor,$row):void{
            if($current->merchant->value!==$actor->merchantId||$current->store->value!==bin2hex($row['store_id'])||$current->connection->value!==bin2hex($row['connection_id'])){throw new AccessDenied('invoice_context_changed');}
            $this->validate($actor,$row);
        },function(ConnectionContext $current)use($actor,$row):void{
            // Lock order shared with cancellation: store -> intent -> external operation.
            (new AccessPolicy($this->db))->require($actor,'invoices.issue',$current->store->value);
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR SHARE',[$row['merchant_id'],$row['store_id']]);
            $this->active($actor,$row,' FOR UPDATE');
        },function(ConnectionContext $current,ExternalLease $lease,ExternalId $reference)use($actor,$row,$result):void{
            $document=$result->document;
            if($document===null||$document->id->value!==$reference->value){throw new \LogicException('Verified invoice result missing.');}
            $this->record($actor,$row,$lease->id,$document);
        });
        // Only safe references enter Operations; the full payload stays encrypted in Invoicing.
        return $operations->execute($context,'invoice.issue',$this->businessKey($id),new SafePayload(['invoice_id'=>$id,'version'=>(int)$row['draft_version'],'evidence_sha256'=>bin2hex($row['snapshot_hash'])]),function(OperationKey $key)use($actor,$row,$call,$context,$result):ExternalId{
            $snapshot=$this->validate($actor,$row);
            $document=$call($context,InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot,false),$key),$key);$this->verifyResult($row,$document);$result->document=$document;return $document->id;
        });
    }
    /** Trusted lookup result only; no HTTP accepts a caller-provided fiscal result. */
    public function reconcile(TenantContext $actor,string $store,string $id,int $expectedOperationVersion,InvoiceSnapshot $issued,string $evidenceHash): OperationResult
    {
        if($expectedOperationVersion<1||$expectedOperationVersion===PHP_INT_MAX){throw new \InvalidArgumentException('Invalid reconciliation version.');}
        new SafePayload(['evidence_sha256'=>$evidenceHash]);
        return $this->db->transaction(function()use($actor,$store,$id,$expectedOperationVersion,$issued,$evidenceHash):OperationResult{
            (new AccessPolicy($this->db))->require($actor,'operations.manage',$store);(new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR SHARE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $row=$this->row($actor,$store,$id,' FOR UPDATE');$operation=$this->operation($row,' FOR UPDATE');
            if($row['lifecycle']!=='ACTIVE'||$operation===null){throw new Conflict('invoice_reconciliation_unavailable');}
            $this->verifyResult($row,$issued);
            if($operation['status']==='CONFIRMED'&&(int)$operation['fencing_version']===$expectedOperationVersion+1&&$operation['provider_reference']===$issued->id->value){
                $saved=$this->db->one('SELECT document_envelope FROM invoice_issued_documents WHERE merchant_id=? AND store_id=? AND intent_id=?',[$row['merchant_id'],$row['store_id'],$row['id']]);
                if($saved!==null&&CanonicalJson::encode($this->cipher->openDocument($row,bin2hex($operation['id']),$saved['document_envelope']))===CanonicalJson::encode($this->documentData($issued))){return new OperationResult(bin2hex($operation['id']),\Ordely\Operations\Domain\ExternalState::Confirmed,$issued->id,(int)$operation['fencing_version']);}
            }
            $result=(new ExternalOperations($this->db))->confirmReconciled($actor,bin2hex($operation['id']),$expectedOperationVersion,$issued->id,$evidenceHash);
            $this->record($actor,$row,$result->id,$issued);return $result;
        });
    }
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function validate(TenantContext $actor,array $row): array
    {
        return $this->db->transaction(function()use($actor,$row):array{
            $store=bin2hex($row['store_id']);(new AccessPolicy($this->db))->require($actor,'invoices.issue',$store);
            $this->active($actor,$row);
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
    private function row(TenantContext $actor,string $store,string $id,string $lock=''): array {return $this->db->one('SELECT * FROM invoice_issue_intents WHERE merchant_id=? AND store_id=? AND id=?'.$lock,[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)])??throw new AccessDenied('invoice_intent_unavailable');}
    /** @param array<string,mixed> $row */
    private function active(TenantContext $actor,array $row,string $lock=''): void {if($this->row($actor,bin2hex($row['store_id']),bin2hex($row['id']),$lock)['lifecycle']!=='ACTIVE'){throw new Conflict('invoice_intent_cancelled');}}
    /** @param array<string,mixed> $row
     * @return array<string,mixed>|null */
    private function operation(array $row,string $lock=''): ?array {return $this->db->one("SELECT id,status,fencing_version,attempt_count,provider_reference FROM external_operations WHERE merchant_id=? AND store_id=? AND type='invoice.issue' AND business_key=?".$lock,[$row['merchant_id'],$row['store_id'],hash('sha256',$this->businessKey(bin2hex($row['id']))->value,true)]);}
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function metadata(array $row): array
    {
        $operation=$this->operation($row);$document=$this->db->one('SELECT operation_id,document_envelope FROM invoice_issued_documents WHERE merchant_id=? AND store_id=? AND intent_id=?',[$row['merchant_id'],$row['store_id'],$row['id']]);
        return ['id'=>bin2hex($row['id']),'storeId'=>bin2hex($row['store_id']),'orderId'=>bin2hex($row['order_id']),'draftVersion'=>(int)$row['draft_version'],'intentVersion'=>(int)$row['version'],'status'=>$row['lifecycle']==='CANCELLED'?'CANCELLED':($operation['status']??'PREPARED'),'canCancel'=>$row['lifecycle']==='ACTIVE'&&$operation===null,'operationId'=>$operation===null?null:bin2hex($operation['id']),'operationVersion'=>$operation===null?0:(int)$operation['fencing_version'],'attempts'=>$operation===null?0:(int)$operation['attempt_count'],'providerReference'=>$operation['provider_reference']??null,'documentVerified'=>($operation['status']??null)==='CONFIRMED'&&$document!==null,'document'=>$document===null?null:$this->cipher->openDocument($row,bin2hex($document['operation_id']),$document['document_envelope']),'executionEnabled'=>false,'createdAt'=>$row['created_at'],'cancelledAt'=>$row['cancelled_at']];
    }
    /** @param array<string,mixed> $row */
    private function verifyResult(array $row,InvoiceSnapshot $issued): void
    {
        $snapshot=$this->cipher->open($row);$draft=InvoiceAssembly::draft($snapshot,FiscalPreparation::build($snapshot,false),$this->businessKey(bin2hex($row['id'])));
        if($issued->creditNote||$issued->cancelled||$issued->originalId!==null||!$issued->total->currency->same($draft->total->currency)||$issued->total->minor!==$draft->total->minor||mb_strlen($issued->number)>255||preg_match('/[\x00-\x1f\x7f]/',$issued->number)){throw new ProviderFailure(ErrorCategory::Unknown);}
    }
    /** @param array<string,mixed> $row */
    private function record(TenantContext $actor,array $row,string $operation,InvoiceSnapshot $issued): void
    {
        $data=$this->documentData($issued);
        $envelope=$this->cipher->sealDocument($row,$operation,$data);
        $this->db->run('INSERT INTO invoice_issued_documents(merchant_id,store_id,intent_id,operation_id,connection_id,reference_hash,document_envelope) VALUES(?,?,?,?,?,?,?)',[$row['merchant_id'],$row['store_id'],$row['id'],Id::bytes($operation),$row['connection_id'],hash('sha256',$issued->id->value,true),$envelope]);
        $this->audit($actor,bin2hex($row['store_id']),bin2hex($row['id']),AuditAction::InvoiceDocumentRecorded,(int)$row['draft_version']);
    }
    /** @return array<string,mixed> */
    private function documentData(InvoiceSnapshot $issued): array {return ['id'=>$issued->id->value,'number'=>$issued->number,'total'=>['minor'=>(string)$issued->total->minor,'decimal'=>$issued->total->format(),'currency'=>$issued->total->currency->code,'exponent'=>$issued->total->currency->exponent],'creditNote'=>false,'cancelled'=>false];}
    private function audit(TenantContext $actor,string $store,string $id,AuditAction $action,int $version): void {(new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),$action,$id,new SafePayload(['invoice_id'=>$id,'version'=>$version]));}
}
