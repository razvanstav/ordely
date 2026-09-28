<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Domain\{AccessDenied,Role};
use Ordely\Identity\Infrastructure\Provisioner;
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Invoicing\Infrastructure\DraftRepository;
use Ordely\Operations\Domain\Conflict;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,InvoiceFixtures as F};

final class InvoiceDraftRepositoryTest extends DatabaseTestCase
{
    private function repository(): DraftRepository { return new DraftRepository($this->db,F::cipher()); }
    public function testCreateReplayAndAuditAreAtomicWithoutPlaintextOrProviderCalls(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=Id::new();$repo=$this->repository();
        $ids=[$repo->create($actor,$store,$id,F::document()),$repo->create($actor,$store,$id,F::document())];self::assertSame([$id,$id],$ids);
        foreach(['invoice_drafts','invoice_draft_revisions','audit_logs','outbox_events'] as $table){
            $rows=$this->db->run('SELECT * FROM '.$table.' WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchAll();self::assertCount(1,$rows);
            self::assertStringNotContainsString('SYNTHETIC-PRIVATE-RECIPIENT',serialize($rows));self::assertStringNotContainsString('SYNTHETIC-PRIVATE-ADDRESS',serialize($rows));
        }
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM external_operations WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        $changed=F::data();$changed['reference']='CHANGED';$this->expectException(Conflict::class);$repo->create($actor,$store,$id,DraftDocument::fromArray($changed));
    }
    public function testEditKeepsEncryptedHistoryAndArchivePreventsFurtherEdits(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=Id::new();$repo=$this->repository();$repo->create($actor,$store,$id,F::document());
        $changed=F::data();$changed['lines'][0]['tax']='4.00';$repo->replace($actor,$store,$id,1,DraftDocument::fromArray($changed));
        $read=$repo->get($actor,$store,$id);self::assertSame(2,$read['version']);self::assertSame('24.00',$read['totals']['total']);
        $old=(string)$this->db->run('SELECT document_envelope FROM invoice_draft_revisions WHERE draft_id=? AND version=1',[Id::bytes($id)])->fetchColumn();self::assertSame('23.00',F::cipher()->open($actor->merchantId,$store,$id,1,$old)->totals()['total']);
        try{$repo->replace($actor,$store,$id,1,F::document());self::fail('Expected stale version rejection.');}catch(Conflict){self::addToAssertionCount(1);}
        $repo->archive($actor,$store,$id,2);$archived=$repo->get($actor,$store,$id);self::assertSame('ARCHIVED',$archived['status']);self::assertSame(3,$archived['version']);
        self::assertCount(0,$repo->list($actor,$store)['drafts']);self::assertCount(1,$repo->list($actor,$store,'ARCHIVED')['drafts']);
        $this->expectException(Conflict::class);$repo->replace($actor,$store,$id,3,F::document());
    }
    public function testTenantStoreGrantsAndCompositeFkPreventCrossingScope(): void
    {
        $actor=$this->tenant();$other=$this->tenant();$store=$this->store($actor);$hidden=$this->store($actor);$foreign=$this->store($other);$id=Id::new();$repo=$this->repository();$repo->create($actor,$store,$id,F::document());
        foreach([[$other,$foreign],[$actor,$hidden],[$actor,$foreign]] as [$ctx,$scope]){try{$repo->get($ctx,$scope,$id);self::fail('Expected scope rejection.');}catch(AccessDenied){self::addToAssertionCount(1);}}
        try{$this->db->run('INSERT INTO invoice_drafts(id,merchant_id,store_id) VALUES(?,?,?)',[Id::bytes(Id::new()),Id::bytes($actor->merchantId),Id::bytes($foreign)]);self::fail('Expected FK rejection.');}catch(\PDOException $error){self::assertSame('23000',$error->getCode());}
        $this->db->run('UPDATE memberships SET all_stores=0 WHERE id=?',[Id::bytes($actor->membershipId)]);
        (new Provisioner($this->db))->grantStore($actor->merchantId,$actor->membershipId,$store);self::assertCount(1,$repo->list($actor,$store)['drafts']);
        $this->db->run('DELETE FROM membership_store_grants WHERE merchant_id=?',[Id::bytes($actor->merchantId)]);
        $this->expectException(AccessDenied::class);$repo->replace($actor,$store,$id,1,F::document());
    }
    public function testFinanceCanPrepareOperatorCanOnlyReadAndViewerIsDenied(): void
    {
        $finance=$this->tenant(Role::Finance);$store=$this->store($finance);$id=Id::new();$repo=$this->repository();$repo->create($finance,$store,$id,F::document());
        self::assertSame('23.00',$repo->get($finance,$store,$id)['totals']['total']);
        $this->db->run("UPDATE memberships SET role='operator' WHERE id=?",[Id::bytes($finance->membershipId)]);self::assertCount(1,$repo->list($finance,$store)['drafts']);
        try{$repo->archive($finance,$store,$id,1);self::fail('Expected write denial.');}catch(AccessDenied){self::addToAssertionCount(1);}
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?",[Id::bytes($finance->membershipId)]);
        $this->expectException(AccessDenied::class);$repo->get($finance,$store,$id);
    }
    public function testFailedEncryptionRollsBackNewDraftAndAudit(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$large=F::data();$line=$large['lines'][0];$large['lines']=[];
        for($i=0;$i<50;++$i){$copy=$line;$copy['id']=Id::new();$copy['description']=str_repeat('🧵',255);$large['lines'][]=$copy;}
        try{$this->repository()->create($actor,$store,Id::new(),DraftDocument::fromArray($large));self::fail('Expected size rejection.');}catch(\InvalidArgumentException){
            foreach(['invoice_drafts','invoice_draft_revisions','audit_logs','outbox_events'] as $table){self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM '.$table.' WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());}
        }
    }
    public function testPaginationIsStableAndDoesNotExposeRecipientsInList(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$repo=$this->repository();
        for($i=0;$i<26;++$i){$repo->create($actor,$store,Id::new(),F::document());}
        $first=$repo->list($actor,$store);self::assertCount(25,$first['drafts']);self::assertNotNull($first['nextCursor']);
        $second=$repo->list($actor,$store,after:$first['nextCursor']);self::assertCount(1,$second['drafts']);self::assertNull($second['nextCursor']);
        $ids=[...array_column($first['drafts'],'id'),...array_column($second['drafts'],'id')];self::assertCount(26,array_unique($ids));
        self::assertStringNotContainsString('SYNTHETIC-PRIVATE-RECIPIENT',json_encode($first,JSON_THROW_ON_ERROR));
    }
}
