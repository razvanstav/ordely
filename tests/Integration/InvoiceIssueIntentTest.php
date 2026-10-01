<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Data\InvoiceDraft;
use Ordely\Core\Value\{ExternalId,OperationKey};
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Invoicing\Infrastructure\IssueCipher;
use Ordely\Operations\Domain\{Conflict,ExternalState};
use Ordely\Operations\Infrastructure\ExternalOperations;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,IntegrationFixtures,IssueFixtures as F,ProcessHarness};

final class InvoiceIssueIntentTest extends CommittedDatabaseTestCase
{
    public function testConfirmedIntentIsImmutableEncryptedAndReplayedWithoutAnotherCall(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$cipher=IntegrationFixtures::cipher();$fixture=F::ready($this->db,$actor,$store,$cipher);$service=F::service($this->db,$cipher);
        $intent=$service->prepare($actor,$store,$fixture['order'],2);self::assertSame('PREPARED',$intent['status']);self::assertFalse($intent['executionEnabled']);
        self::assertSame($intent,$service->prepare($actor,$store,$fixture['order'],2));
        $raw=$this->db->one('SELECT * FROM invoice_issue_intents WHERE id=?',[Id::bytes($intent['id'])]);self::assertNotNull($raw);
        self::assertStringNotContainsString('SYNTHETIC-PREP',$raw['snapshot_envelope']);self::assertSame('SYNTHETIC-PREP-COMPANY',(new IssueCipher($cipher))->open($raw)['customer']['name']);
        $calls=0;$key=null;$callback=function(ConnectionContext $context,InvoiceDraft $draft,OperationKey $providerKey)use(&$calls,&$key,$actor,$store):ExternalId{++$calls;$key=$providerKey->value;self::assertFalse($this->db->pdo->inTransaction());self::assertSame($actor->merchantId,$context->merchant->value);self::assertSame($store,$context->store->value);self::assertSame(2420,$draft->total->minor);self::assertSame($providerKey->value,$draft->clientReference->value);return new ExternalId('SYNTHETIC-ISSUED');};
        $first=$service->execute($actor,$store,$intent['id'],$callback);$second=F::service(self::database(),$cipher)->execute($actor,$store,$intent['id'],$callback);
        self::assertSame(ExternalState::Confirmed,$first->state);self::assertSame($first->id,$second->id);self::assertSame(1,$calls);self::assertSame('operation:'.$first->id,$key);
        self::assertSame('CONFIRMED',$service->get($actor,$store,$intent['id'])['status']);
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_issue_prepared'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND safe_data LIKE '%SYNTHETIC%'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertStringNotContainsString('SYNTHETIC-PREP',(string)$this->db->run('SELECT CONCAT(request_hash,business_key) FROM external_operations WHERE id=?',[Id::bytes($first->id)])->fetchColumn());
        $this->db->run('DELETE FROM commerce_records WHERE id=?',[Id::bytes($fixture['order'])]);self::assertSame('CONFIRMED',$service->get($actor,$store,$intent['id'])['status']);
    }
    public function testCertainFailureRetriesSameKeyButUnknownNeedsReconciliation(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$service=F::service($this->db,IntegrationFixtures::cipher());$fixture=F::ready($this->db,$actor,$store,IntegrationFixtures::cipher());$intent=$service->prepare($actor,$store,$fixture['order'],2);$keys=[];
        $retry=$service->execute($actor,$store,$intent['id'],function(ConnectionContext $context,InvoiceDraft $draft,OperationKey $key)use(&$keys):ExternalId{$keys[]=$key->value;throw new ProviderFailure(ErrorCategory::Transient,90);});self::assertSame(ExternalState::Retryable,$retry->state);
        $this->db->run('UPDATE external_operations SET next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[Id::bytes($retry->id)]);
        $unknown=$service->execute($actor,$store,$intent['id'],function(ConnectionContext $context,InvoiceDraft $draft,OperationKey $key)use(&$keys):ExternalId{$keys[]=$key->value;throw new ProviderFailure(ErrorCategory::Unknown);});self::assertSame(ExternalState::Unknown,$unknown->state);self::assertSame($keys[0],$keys[1]);
        $noReplay=static fn():ExternalId=>throw new \LogicException('Unknown must not replay');self::assertSame(ExternalState::Unknown,$service->execute($actor,$store,$intent['id'],$noReplay)->state);
        (new ExternalOperations($this->db))->confirmReconciled($actor,$unknown->id,$unknown->version,new ExternalId('SYNTHETIC-FOUND'),hash('sha256','synthetic evidence'));
        self::assertSame('SYNTHETIC-FOUND',$service->execute($actor,$store,$intent['id'],$noReplay)->requireConfirmed()->value);self::assertSame(2,$service->get($actor,$store,$intent['id'])['attempts']);
    }
    public function testChangedSourceDraftProfileConnectionAndAccessNeverReachProvider(): void
    {
        $actor=$this->tenant();$cipher=IntegrationFixtures::cipher();
        foreach(['source','draft','profile','connection','binding','access'] as $mutation){
            $store=$this->store($actor);$fixture=F::ready($this->db,$actor,$store,$cipher);$service=F::service($this->db,$cipher);$intent=$service->prepare($actor,$store,$fixture['order'],2);
            match($mutation){'source'=>$this->db->run('UPDATE commerce_records SET version=4 WHERE id=?',[Id::bytes($fixture['order'])]),'draft'=>F::drafts($this->db,$cipher)->complete($actor,$store,$fixture['order'],2,array_replace_recursive(\Ordely\Tests\Support\PreparationFixtures::fiscalDetails(),['document'=>['dueOn'=>'2026-10-03']])),'profile'=>$this->db->run('DELETE FROM invoice_profiles WHERE merchant_id=? AND store_id=?',[Id::bytes($actor->merchantId),Id::bytes($store)]),'connection'=>$this->db->run('UPDATE provider_connections SET version=3 WHERE id=?',[Id::bytes($fixture['connection'])]),'binding'=>$this->db->run('DELETE FROM store_provider_bindings WHERE connection_id=?',[Id::bytes($fixture['connection'])]),'access'=>$this->db->run('UPDATE memberships SET all_stores=0 WHERE id=?',[Id::bytes($actor->membershipId)])};
            try{$service->execute($actor,$store,$intent['id'],static fn():ExternalId=>throw new \LogicException('Stale intent reached provider'));self::fail('Expected stale intent rejection');}catch(Conflict|AccessDenied){self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM external_attempts a JOIN external_operations o ON a.operation_id=o.id WHERE o.store_id=?',[Id::bytes($store)])->fetchColumn());}
            $this->db->run('UPDATE memberships SET all_stores=1 WHERE id=?',[Id::bytes($actor->membershipId)]);
        }
    }
    public function testRollbackAndAuthenticatedSnapshotIdentityPreventUnusableIntent(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$cipher=IntegrationFixtures::cipher();$fixture=F::ready($this->db,$actor,$store,$cipher);$service=F::service($this->db,$cipher);
        $this->db->pdo->beginTransaction();$service->prepare($actor,$store,$fixture['order'],2);$this->db->pdo->rollBack();self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_issue_intents WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        $intent=$service->prepare($actor,$store,$fixture['order'],2);$row=$this->db->one('SELECT * FROM invoice_issue_intents WHERE id=?',[Id::bytes($intent['id'])]);self::assertNotNull($row);
        foreach(['id','merchant_id','store_id','order_id','connection_id','draft_version','connection_version','profile_version'] as $field){$modified=$row;$modified[$field]=str_ends_with($field,'_id')||$field==='id'?Id::bytes(Id::new()):99;try{(new IssueCipher($cipher))->open($modified);self::fail('Expected AAD failure');}catch(\RuntimeException $error){self::assertSame('Invoice issue snapshot unavailable.',$error->getMessage());}}
        $this->expectException(Conflict::class);$service->prepare($actor,$store,$fixture['order'],3);
    }
    public function testConcurrentPreparationAndExecutionAndCrashCannotDuplicate(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$fixture=F::ready($this->db,$actor,$store,IntegrationFixtures::cipher());$args=[$actor->merchantId,$actor->membershipId,$actor->userId,$store];
        $prepared=ProcessHarness::together([['invoice-issue-prepare',...$args,$fixture['order']],['invoice-issue-prepare',...$args,$fixture['order']]]);$ids=[];
        foreach($prepared as $result){self::assertSame(0,$result['exit'],$result['error']);$ids[]=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR)['id'];}self::assertSame($ids[0],$ids[1]);
        $executed=ProcessHarness::together([['invoice-issue-execute',...$args,$ids[0]],['invoice-issue-execute',...$args,$ids[0]]]);$operationIds=[];foreach($executed as $result){self::assertSame(0,$result['exit'],$result['error']);$operationIds[]=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR)['id'];}self::assertSame($operationIds[0],$operationIds[1]);self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM external_attempts WHERE operation_id=?',[Id::bytes($operationIds[0])])->fetchColumn());
        $store2=$this->store($actor);$fixture2=F::ready($this->db,$actor,$store2,IntegrationFixtures::cipher());$service=F::service($this->db,IntegrationFixtures::cipher());$intent=$service->prepare($actor,$store2,$fixture2['order'],2);
        $crash=ProcessHarness::together([['invoice-issue-crash',$actor->merchantId,$actor->membershipId,$actor->userId,$store2,$intent['id']]])[0];self::assertSame(23,$crash['exit']);
        $this->db->run('UPDATE external_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE store_id=?',[Id::bytes($store2)]);
        self::assertSame(ExternalState::Unknown,$service->execute($actor,$store2,$intent['id'],static fn():ExternalId=>throw new \LogicException('Crash must not replay'))->state);
    }
}
