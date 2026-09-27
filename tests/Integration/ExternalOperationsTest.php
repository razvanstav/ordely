<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Contracts\{ConnectionContext,ErrorCategory,ProviderFailure};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId,ExternalId,OperationKey};
use Ordely\Identity\Domain\TenantContext;
use Ordely\Operations\Domain\{Conflict,ExternalState,SafePayload};
use Ordely\Operations\Infrastructure\ExternalOperations;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,ProcessHarness};

final class ExternalOperationsTest extends CommittedDatabaseTestCase
{
    /** @var list<string> */
    private array $simulatedTenants=[];
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::database()->run('CREATE TABLE IF NOT EXISTS test_external_effects(merchant_id BINARY(16) NOT NULL,provider_key VARCHAR(128) NOT NULL,PRIMARY KEY(merchant_id,provider_key)) ENGINE=InnoDB');
    }
    protected function tearDown(): void
    {
        foreach($this->simulatedTenants as $merchant){$this->db->run('DELETE FROM test_external_effects WHERE merchant_id=?',[Id::bytes($merchant)]);}parent::tearDown();
    }
    private function context(TenantContext $tenant): ConnectionContext
    {
        $this->simulatedTenants[]=$tenant->merchantId;
        return new ConnectionContext(new MerchantId($tenant->merchantId),new StoreId($this->store($tenant)),ConnectionId::new(),CorrelationId::new());
    }
    public function testConfirmedIntentSurvivesNewCoordinatorAndDifferentHttpCorrelation(): void
    {
        $tenant=$this->tenant();$ctx=$this->context($tenant);$calls=0;$callback=function(OperationKey $key)use(&$calls):ExternalId{++$calls;self::assertStringStartsWith('operation:',$key->value);self::assertFalse($this->db->pdo->inTransaction());return new ExternalId('TEST-1');};
        $first=(new ExternalOperations($this->db))->execute($ctx,'test.emit',new OperationKey('business-1'),new SafePayload(),$callback);
        $newCtx=new ConnectionContext($ctx->merchant,$ctx->store,$ctx->connection,CorrelationId::new());
        $second=(new ExternalOperations(self::database()))->execute($newCtx,'test.emit',new OperationKey('business-1'),new SafePayload(),$callback);
        self::assertSame(1,$calls);self::assertSame($first->id,$second->id);self::assertSame('TEST-1',$second->requireConfirmed()->value);
        $this->expectException(Conflict::class);(new ExternalOperations($this->db))->execute($ctx,'test.emit',new OperationKey('business-1'),new SafePayload(['count'=>1]),$callback);
    }
    public function testTwoProcessesWithSameIntentProduceOneExternalEffect(): void
    {
        $tenant=$this->tenant();$ctx=$this->context($tenant);$command=['external',$tenant->merchantId,$ctx->store->value,$ctx->connection->value];
        $results=ProcessHarness::together([$command,$command]);$ids=[];$states=[];
        foreach($results as $result){self::assertSame(0,$result['exit'],$result['error']);$data=json_decode($result['output'],true,flags:JSON_THROW_ON_ERROR);$ids[]=$data['id'];$states[]=$data['state'];}
        self::assertSame($ids[0],$ids[1]);self::assertContains('CONFIRMED',$states);
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM test_external_effects WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM external_attempts WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testProcessCrashAfterEffectRequiresReconciliationBeforeAnyReplay(): void
    {
        $tenant=$this->tenant();$ctx=$this->context($tenant);
        $child=ProcessHarness::together([['external-crash',$tenant->merchantId,$ctx->store->value,$ctx->connection->value]])[0];self::assertSame(23,$child['exit'],$child['error']);
        self::assertSame('IN_FLIGHT',$this->db->run('SELECT status FROM external_operations WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        $this->db->run('UPDATE external_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE merchant_id=?',[Id::bytes($tenant->merchantId)]);
        $calls=0;$call=function(OperationKey $key)use(&$calls):ExternalId{++$calls;return new ExternalId('DUPLICATE');};$service=new ExternalOperations(self::database());
        $result=$service->execute($ctx,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$ctx->store->value]),$call);
        self::assertSame(ExternalState::Unknown,$result->state);
        $again=$service->execute($ctx,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$ctx->store->value]),$call);
        self::assertSame(ExternalState::Unknown,$again->state);
        $confirmed=$service->confirmReconciled($tenant,$result->id,$result->version,new ExternalId('TEST-FOUND-EXTERNALLY'),hash('sha256','synthetic external receipt'));
        self::assertSame(ExternalState::Confirmed,$confirmed->state);
        $replay=$service->execute($ctx,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$ctx->store->value]),$call);
        self::assertSame('TEST-FOUND-EXTERNALLY',$replay->requireConfirmed()->value);self::assertSame(0,$calls);
        self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM test_external_effects WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='external_reconciled'",[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testTransientFailureRetriesSameProviderKeyAndUnknownFailureDoesNot(): void
    {
        $ctx=$this->context($this->tenant());$service=new ExternalOperations($this->db);$keys=[];
        $first=$service->execute($ctx,'test.emit',new OperationKey('transient'),new SafePayload(),function(OperationKey $key)use(&$keys):ExternalId{$keys[]=$key->value;throw new ProviderFailure(ErrorCategory::Transient,180);});
        self::assertSame(ExternalState::Retryable,$first->state);
        self::assertSame(180,$first->retryAfterSeconds);
        $waiting=$service->execute($ctx,'test.emit',new OperationKey('transient'),new SafePayload(),fn(OperationKey $key):ExternalId=>new ExternalId('TOO-EARLY'));
        self::assertSame(ExternalState::Retryable,$waiting->state);
        $this->db->run('UPDATE external_operations SET next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[Id::bytes($first->id)]);
        $second=$service->execute($ctx,'test.emit',new OperationKey('transient'),new SafePayload(),function(OperationKey $key)use(&$keys):ExternalId{$keys[]=$key->value;return new ExternalId('RETRIED');});
        self::assertSame(ExternalState::Confirmed,$second->state);self::assertSame($keys[0],$keys[1]);
        $unknown=$service->execute($ctx,'test.emit',new OperationKey('unknown'),new SafePayload(),function(OperationKey $key):ExternalId{throw new \RuntimeException('SECRET-DIAGNOSTIC');});
        self::assertSame(ExternalState::Unknown,$unknown->state);
        self::assertSame('unknown',$this->db->run('SELECT safe_error FROM external_operations WHERE id=?',[Id::bytes($unknown->id)])->fetchColumn());
    }
    public function testReconciliationRejectsForeignTenantAndStaleVersion(): void
    {
        $tenant=$this->tenant();$foreign=$this->tenant();$ctx=$this->context($tenant);$service=new ExternalOperations($this->db);
        $unknown=$service->execute($ctx,'test.emit',new OperationKey('unknown'),new SafePayload(),fn(OperationKey $key):ExternalId=>throw new ProviderFailure(ErrorCategory::Unknown));
        try{$service->confirmReconciled($foreign,$unknown->id,$unknown->version,new ExternalId('REF'),hash('sha256','evidence'));self::fail('Expected foreign scope rejection.');}catch(\Ordely\Identity\Domain\AccessDenied){self::addToAssertionCount(1);}
        $this->expectException(Conflict::class);$service->confirmReconciled($tenant,$unknown->id,$unknown->version+1,new ExternalId('REF'),hash('sha256','evidence'));
    }
    public function testCrashBeforeSendingIsStillUncertainToTheRecoveringProcess(): void
    {
        $tenant=$this->tenant();$ctx=$this->context($tenant);
        $child=ProcessHarness::together([['external-crash-before',$tenant->merchantId,$ctx->store->value,$ctx->connection->value]])[0];self::assertSame(24,$child['exit'],$child['error']);
        $this->db->run('UPDATE external_operations SET lease_until=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE merchant_id=?',[Id::bytes($tenant->merchantId)]);
        $calls=0;$result=(new ExternalOperations($this->db))->execute($ctx,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$ctx->store->value]),function(OperationKey $key)use(&$calls):ExternalId{++$calls;return new ExternalId('UNSAFE-RETRY');});
        self::assertSame(ExternalState::Unknown,$result->state);self::assertSame(0,$calls);
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM test_external_effects WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testLateExternalResponseCannotOverwriteNewerFencingVersion(): void
    {
        $tenant=$this->tenant();$ctx=$this->context($tenant);
        try{
            (new ExternalOperations($this->db))->execute($ctx,'test.emit',new OperationKey('late'),new SafePayload(),function(OperationKey $key)use($tenant):ExternalId{
                self::database()->run("UPDATE external_operations SET status='UNKNOWN',lease_owner=NULL,fencing_version=fencing_version+1 WHERE merchant_id=?",[Id::bytes($tenant->merchantId)]);
                return new ExternalId('LATE-REFERENCE');
            });self::fail('Expected fencing rejection.');
        }catch(\Ordely\Operations\Domain\LeaseLost){self::assertSame('UNKNOWN',$this->db->run('SELECT status FROM external_operations WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());}
        self::assertNull($this->db->run('SELECT provider_reference FROM external_operations WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testExternalRetryBudgetStopsAfterFiveCertainFailures(): void
    {
        $ctx=$this->context($this->tenant());$service=new ExternalOperations($this->db);$calls=0;
        $callback=function(OperationKey $key)use(&$calls):ExternalId{++$calls;throw new ProviderFailure(ErrorCategory::Transient);};
        for($i=0;$i<6;++$i){
            $result=$service->execute($ctx,'test.emit',new OperationKey('budget'),new SafePayload(),$callback);
            $this->db->run('UPDATE external_operations SET next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[Id::bytes($result->id)]);
        }
        self::assertSame(5,$calls);self::assertSame(ExternalState::Failed,$result->state);
    }
}
