<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Core\Value\CanonicalJson;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,EventType,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{AuditLog,Outbox};
use Ordely\Shared\Id;

final readonly class DraftRepository
{
    public function __construct(private Sql $db,private DraftCipher $cipher) {}
    public function create(TenantContext $actor,string $store,string $id,DraftDocument $document): string
    {
        return $this->db->transaction(function()use($actor,$store,$id,$document):string{
            $this->access($actor,$store,'invoices.draft');
            $created=$this->db->run('INSERT INTO invoice_drafts(id,merchant_id,store_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=id',[Id::bytes($id),Id::bytes($actor->merchantId),Id::bytes($store)])->rowCount()===1;
            $row=$this->db->one('SELECT * FROM invoice_drafts WHERE merchant_id=? AND store_id=? AND id=? FOR UPDATE',$this->scope($actor,$store,$id));
            if($row===null||$row['status']!=='DRAFT'){throw new Conflict('draft_creation_conflict');}
            if(!$created){
                $existing=$this->document($actor,$store,$id,(int)$row['version']);
                if(!hash_equals(CanonicalJson::encode($existing->data()),CanonicalJson::encode($document->data()))){throw new Conflict('draft_creation_conflict');}
                return $id;
            }
            $this->revision($actor,$store,$id,1,$document);
            $this->record($actor,$store,$id,1,AuditAction::InvoiceDraftCreated,EventType::InvoiceDraftCreated);return $id;
        });
    }
    public function replace(TenantContext $actor,string $store,string $id,int $expectedVersion,DraftDocument $document): void
    {
        $this->db->transaction(function()use($actor,$store,$id,$expectedVersion,$document):void{
            $this->lock($actor,$store,$id,$expectedVersion);
            $this->revision($actor,$store,$id,$expectedVersion+1,$document);
            $this->db->run('UPDATE invoice_drafts SET version=version+1 WHERE merchant_id=? AND store_id=? AND id=?',$this->scope($actor,$store,$id));
            $this->record($actor,$store,$id,$expectedVersion+1,AuditAction::InvoiceDraftUpdated,EventType::InvoiceDraftUpdated);
        });
    }
    public function archive(TenantContext $actor,string $store,string $id,int $expectedVersion): void
    {
        $this->db->transaction(function()use($actor,$store,$id,$expectedVersion):void{
            $this->lock($actor,$store,$id,$expectedVersion);
            $document=$this->document($actor,$store,$id,$expectedVersion);
            $this->revision($actor,$store,$id,$expectedVersion+1,$document);
            $this->db->run("UPDATE invoice_drafts SET status='ARCHIVED',version=version+1 WHERE merchant_id=? AND store_id=? AND id=?",$this->scope($actor,$store,$id));
            $this->record($actor,$store,$id,$expectedVersion+1,AuditAction::InvoiceDraftArchived,EventType::InvoiceDraftArchived);
        });
    }
    /** @return array<string,mixed> */
    public function get(TenantContext $actor,string $store,string $id): array
    {
        return $this->db->transaction(function()use($actor,$store,$id):array{
            $this->access($actor,$store,'invoices.read');
            $row=$this->db->one('SELECT * FROM invoice_drafts WHERE merchant_id=? AND store_id=? AND id=?',$this->scope($actor,$store,$id));
            if($row===null){throw new AccessDenied('draft_unavailable');}
            $document=$this->document($actor,$store,$id,(int)$row['version']);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::InvoiceDraftViewed,$id,new SafePayload(['invoice_id'=>$id,'version'=>(int)$row['version']]));
            return ['id'=>$id,'storeId'=>$store,'status'=>$row['status'],'version'=>(int)$row['version'],'document'=>$document->data(),'totals'=>$document->totals()];
        });
    }
    /** @return array{drafts:list<array<string,mixed>>,nextCursor:?string} */
    public function list(TenantContext $actor,string $store,string $status='DRAFT',?string $after=null): array
    {
        if(!in_array($status,['DRAFT','ARCHIVED'],true)){throw new \InvalidArgumentException('Invalid draft filter.');}
        return $this->db->transaction(function()use($actor,$store,$status,$after):array{
            $this->access($actor,$store,'invoices.read');$params=[Id::bytes($actor->merchantId),Id::bytes($store),$status];if($after!==null){$params[]=Id::bytes($after);}
            $rows=$this->db->run('SELECT d.id,d.status,d.version,d.updated_at,r.document_envelope FROM invoice_drafts d JOIN invoice_draft_revisions r ON r.merchant_id=d.merchant_id AND r.store_id=d.store_id AND r.draft_id=d.id AND r.version=d.version WHERE d.merchant_id=? AND d.store_id=? AND d.status=?'.($after===null?'':' AND d.id>?').' ORDER BY d.id LIMIT 26',$params)->fetchAll();
            $more=count($rows)>25;$drafts=[];
            foreach(array_slice($rows,0,25) as $row){
                $id=bin2hex((string)$row['id']);$document=$this->cipher->open($actor->merchantId,$store,$id,(int)$row['version'],(string)$row['document_envelope']);
                $drafts[]=['id'=>$id,'status'=>$row['status'],'version'=>(int)$row['version'],'reference'=>$document->reference,'lineCount'=>count($document->lines),'totals'=>$document->totals(),'updatedAt'=>$row['updated_at']];
            }
            return ['drafts'=>$drafts,'nextCursor'=>$more?(string)$drafts[count($drafts)-1]['id']:null];
        });
    }
    private function document(TenantContext $actor,string $store,string $id,int $version): DraftDocument
    {
        // A duplicate insert may wait for another transaction after our snapshot was opened.
        // Read the committed revision with the same current-read semantics as the draft lock.
        $row=$this->db->one('SELECT document_envelope FROM invoice_draft_revisions WHERE merchant_id=? AND store_id=? AND draft_id=? AND version=? FOR SHARE',[...$this->scope($actor,$store,$id),$version]);
        if($row===null){throw new \RuntimeException('Invoice draft unavailable.');}
        return $this->cipher->open($actor->merchantId,$store,$id,$version,(string)$row['document_envelope']);
    }
    private function revision(TenantContext $actor,string $store,string $id,int $version,DraftDocument $document): void
    {
        $envelope=$this->cipher->seal($actor->merchantId,$store,$id,$version,$document);
        $this->db->run('INSERT INTO invoice_draft_revisions(merchant_id,store_id,draft_id,version,document_envelope) VALUES(?,?,?,?,?)',[...$this->scope($actor,$store,$id),$version,$envelope]);
    }
    private function lock(TenantContext $actor,string $store,string $id,int $version): void
    {
        $this->access($actor,$store,'invoices.draft');
        $row=$this->db->one('SELECT status,version FROM invoice_drafts WHERE merchant_id=? AND store_id=? AND id=? FOR UPDATE',$this->scope($actor,$store,$id));
        if($row===null){throw new AccessDenied('draft_unavailable');}
        if($row['status']!=='DRAFT'||$version<1||$version>=4294967295||(int)$row['version']!==$version){throw new Conflict('draft_version_conflict');}
    }
    private function access(TenantContext $actor,string $store,string $permission): void { (new AccessPolicy($this->db))->require($actor,$permission,$store); }
    /** @return list<string> */
    private function scope(TenantContext $actor,string $store,string $id): array { return [Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)]; }
    private function record(TenantContext $actor,string $store,string $id,int $version,AuditAction $action,EventType $event): void
    {
        $scope=new Scope($actor->merchantId,$store);$payload=new SafePayload(['invoice_id'=>$id,'version'=>$version]);$correlation=Id::new();
        (new AuditLog($this->db))->append($scope,Actor::user($actor->userId),$action,$id,$payload,$correlation);
        (new Outbox($this->db))->append($scope,$event,$id,$version,$payload,$correlation);
    }
}
