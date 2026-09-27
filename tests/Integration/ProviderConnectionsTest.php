<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Contracts\{ConnectionContext,CommerceConnector,CarrierProvider,InvoiceProvider,Capability};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId,OperationKey,ExternalId};
use Ordely\Identity\Domain\{AccessDenied,Role,TenantContext};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use Ordely\Integrations\Infrastructure\{Connections,ConnectionGuard,ConnectionResolver,KeyRing,SecretCipher};
use Ordely\Operations\Domain\{Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{ExternalOperations,MySqlJobQueue};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,IntegrationFixtures as F,ProcessHarness};

final class ProviderConnectionsTest extends CommittedDatabaseTestCase
{
    private function service(?SecretCipher $cipher=null): Connections { return new Connections($this->db,F::registry(),$cipher??F::cipher()); }
    private function context(TenantContext $tenant,string $store,string $id): ConnectionContext { return new ConnectionContext(new MerchantId($tenant->merchantId),new StoreId($store),new ConnectionId($id),CorrelationId::new()); }
    public function testEncryptedPersistenceReplayAndAuditNeverExposeCredentials(): void
    {
        $tenant=$this->tenant();$id=Id::new();$secret=new Secrets(['apiToken'=>'SYNTHETIC-persisted-secret']);$service=$this->service();
        $results=[$service->create($tenant,$id,'fake-carrier','Courier',$secret),$service->create($tenant,$id,'fake-carrier','Courier',$secret)];self::assertSame([$id,$id],$results);
        self::assertCount(1,$service->list($tenant));
        $stored=(string)$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();
        self::assertStringNotContainsString('SYNTHETIC-persisted-secret',$stored);self::assertSame($secret->reveal(),F::cipher()->decrypt($tenant->merchantId,$id,'fake-carrier',$stored)->reveal());
        $audit=$this->db->run('SELECT * FROM audit_logs WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(1,$audit);self::assertStringNotContainsString('SYNTHETIC-persisted-secret',json_encode($service->list($tenant),JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SYNTHETIC-persisted-secret',serialize($audit));
        $this->expectException(Conflict::class);$service->create($tenant,$id,'fake-carrier','Courier',new Secrets(['apiToken'=>'changed']));
    }
    public function testReencryptionAndCredentialReplacementUseVersionChecks(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$id=F::connection($this->db,$tenant,$store);
        $rotated=new SecretCipher(new KeyRing('next',['test'=>str_repeat('t',32),'next'=>str_repeat('n',32)]));$service=$this->service($rotated);$service->rotate($tenant,$id,2);
        self::assertSame(3,$service->list($tenant)[0]['version']);self::assertSame('next',$service->list($tenant)[0]['keyId']);
        $service->rotate($tenant,$id,3,new Secrets(['apiToken'=>'replacement-test-token']));
        $stored=(string)$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();
        $onlyNew=new SecretCipher(new KeyRing('next',['next'=>str_repeat('n',32)]));self::assertSame(['apiToken'=>'replacement-test-token'],$onlyNew->decrypt($tenant->merchantId,$id,'fake-carrier',$stored)->reveal());
        $this->expectException(Conflict::class);$service->rotate($tenant,$id,3);
    }
    public function testMissingOldKeyRollsBackMutationAndAudit(): void
    {
        $tenant=$this->tenant();$id=F::connection($this->db,$tenant,$this->store($tenant));$before=(string)$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn();
        try{$this->service(new SecretCipher(new KeyRing('wrong',['wrong'=>random_bytes(32)])))->rotate($tenant,$id,2);self::fail('Expected missing key.');}catch(\RuntimeException){self::assertSame(2,$this->service()->list($tenant)[0]['version']);}
        self::assertSame($before,$this->db->run('SELECT credentials_envelope FROM provider_connections WHERE id=?',[Id::bytes($id)])->fetchColumn());
        self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='connection_reencrypted'",[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testMerchantAndStoreAssociationAreRequiredByResolverAndCompositeForeignKeys(): void
    {
        $tenant=$this->tenant();$foreign=$this->tenant();$store=$this->store($tenant);$foreignStore=$this->store($foreign);$id=F::connection($this->db,$tenant,$store);$context=$this->context($tenant,$store,$id);
        $resolver=new ConnectionResolver($this->db,F::registry(),F::cipher());
        $capabilities=$resolver->withProvider($context,ProviderKind::Carrier,static fn(CommerceConnector|CarrierProvider|InvoiceProvider $provider)=>$provider->capabilities($context));self::assertTrue($capabilities->has(Capability::Shipment));
        foreach([$this->context($foreign,$foreignStore,$id),$this->context($tenant,$this->store($tenant),$id)] as $bad){try{(new ConnectionGuard($this->db))->active($bad);self::fail('Expected scope denial.');}catch(AccessDenied){self::addToAssertionCount(1);}}
        try{$this->service()->bind($tenant,$id,2,$foreignStore);self::fail('Expected foreign store denial.');}catch(AccessDenied){self::addToAssertionCount(1);}
        try{$this->db->run("INSERT INTO store_provider_bindings(merchant_id,store_id,connection_id,kind) VALUES(?,?,?,'carrier')",[Id::bytes($foreign->merchantId),Id::bytes($foreignStore),Id::bytes($id)]);self::fail('Expected composite FK denial.');}catch(\PDOException $error){self::assertSame('23000',$error->getCode());}
        $otherStore=$this->store($tenant);
        try{$this->db->run("INSERT INTO store_provider_bindings(merchant_id,store_id,connection_id,kind) VALUES(?,?,?,'invoice')",[Id::bytes($tenant->merchantId),Id::bytes($otherStore),Id::bytes($id)]);self::fail('Expected kind FK denial.');}catch(\PDOException $error){self::assertSame('23000',$error->getCode());}
    }
    public function testDefaultSelectionAndUnbindingAreAtomicAndAudited(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$first=F::connection($this->db,$tenant,$store);$second=F::connection($this->db,$tenant,$store);$service=$this->service();
        self::assertSame($second,bin2hex((string)$this->db->run('SELECT connection_id FROM store_provider_bindings WHERE merchant_id=? AND store_id=? AND is_default=1',[Id::bytes($tenant->merchantId),Id::bytes($store)])->fetchColumn()));
        $service->bind($tenant,$second,2,$store,remove:true);
        try{(new ConnectionGuard($this->db))->active($this->context($tenant,$store,$second));self::fail('Expected unbound rejection.');}catch(AccessDenied){self::addToAssertionCount(1);}
        self::assertSame($first,bin2hex((string)(new ConnectionGuard($this->db))->active($this->context($tenant,$store,$first))['id']));
    }
    public function testRestrictedOrRevokedMembershipCannotManageSharedMerchantSecrets(): void
    {
        foreach([[Role::Admin,false],[Role::Operator,true],[Role::Viewer,true]] as [$role,$all]){
            $actor=$this->tenant($role,$all);try{$this->service()->list($actor);self::fail('Expected access denial.');}catch(AccessDenied){self::addToAssertionCount(1);}
        }
        $actor=$this->tenant();$id=F::connection($this->db,$actor,$this->store($actor));
        $this->db->run('UPDATE memberships SET all_stores=0 WHERE id=?',[Id::bytes($actor->membershipId)]);
        $this->expectException(AccessDenied::class);$this->service()->rotate($actor,$id,2);
    }
    public function testTwoRotationProcessesCannotOverwriteTheSameVersion(): void
    {
        $tenant=$this->tenant();$id=F::connection($this->db,$tenant,$this->store($tenant));$command=['rotate',$tenant->merchantId,$tenant->membershipId,$tenant->userId,$id];$results=ProcessHarness::together([$command,$command]);$states=[];
        foreach($results as $result){self::assertSame(0,$result['exit'],$result['error']);$states[]=trim($result['output']);}sort($states);self::assertSame(['conflict','rotated'],$states);
        self::assertSame(3,$this->service()->list($tenant)[0]['version']);
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='connection_reencrypted'",[Id::bytes($tenant->merchantId)])->fetchColumn());
    }
    public function testQueuedWorkCannotUseRevokedConnectionOrSendExternalEffect(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$id=F::connection($this->db,$tenant,$store);$ctx=$this->context($tenant,$store,$id);$queue=new MySqlJobQueue($this->db);
        $queue->enqueue(new Scope($tenant->merchantId,$store),'test.provider',new SafePayload(['connection_id'=>$id]),new OperationKey('before-revoke'));
        $this->service()->revoke($tenant,$id,2);$job=$queue->claim($tenant->merchantId);self::assertNotNull($job);$calls=0;
        try{(new ExternalOperations($this->db))->execute($ctx,'test.revoked',new OperationKey('old-job'),new SafePayload(),function()use(&$calls):ExternalId{++$calls;return new ExternalId('UNSAFE');});self::fail('Expected revoked denial.');}catch(AccessDenied){self::assertSame(0,$calls);}
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM external_operations WHERE merchant_id=?',[Id::bytes($tenant->merchantId)])->fetchColumn());
        $this->expectException(AccessDenied::class);(new ConnectionResolver($this->db,F::registry(),F::cipher()))->withProvider($ctx,ProviderKind::Carrier,fn()=>null);
    }
    public function testRevocationBetweenReservationAndSendingIsRechecked(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$id=F::connection($this->db,$tenant,$store);$ctx=$this->context($tenant,$store,$id);$checks=0;$calls=0;
        $service=new ExternalOperations($this->db,function(ConnectionContext $context)use(&$checks,$tenant,$id):void{if(++$checks===2){$this->service()->revoke($tenant,$id,2);}});
        $result=$service->execute($ctx,'test.revocation',new OperationKey('revoke-before-call'),new SafePayload(),function()use(&$calls):ExternalId{++$calls;return new ExternalId('UNSAFE');});
        self::assertSame(\Ordely\Operations\Domain\ExternalState::Failed,$result->state);self::assertSame(0,$calls);
    }
    public function testWorkerMarksRevokedConnectionJobDeadWithoutProviderCall(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$id=F::connection($this->db,$tenant,$store);$ctx=$this->context($tenant,$store,$id);$queue=new MySqlJobQueue($this->db);
        $jobId=$queue->enqueue(new Scope($tenant->merchantId,$store),'test.provider',new SafePayload(['connection_id'=>$id]),new OperationKey('worker-revoked'));
        $handler=new class(new ExternalOperations($this->db),$ctx) implements \Ordely\Operations\Application\JobHandler {
            public int $calls=0;
            public function __construct(private ExternalOperations $operations,private ConnectionContext $context) {}
            public function type(): string { return 'test.provider'; }
            public function handle(\Ordely\Operations\Domain\JobLease $job): void { $this->operations->execute($this->context,'test.worker',new OperationKey($job->id),$job->payload,function():ExternalId{++$this->calls;return new ExternalId('UNSAFE');})->requireConfirmed(); }
        };
        $this->service()->revoke($tenant,$id,2);
        self::assertSame('dead',(new \Ordely\Operations\Application\Worker($queue,[$handler]))->once($tenant->merchantId));self::assertSame(0,$handler->calls);
        self::assertSame('scope_inactive',$this->db->run('SELECT last_error FROM jobs WHERE id=?',[Id::bytes($jobId)])->fetchColumn());
    }
    public function testExistingFakeConnectionIsNotUsableInProduction(): void
    {
        $tenant=$this->tenant();$store=$this->store($tenant);$id=F::connection($this->db,$tenant,$store);$before=$_ENV['APP_ENV']??null;
        try{$_ENV['APP_ENV']='prod';$this->expectException(AccessDenied::class);(new ConnectionGuard($this->db))->active($this->context($tenant,$store,$id));}
        finally{if($before===null){unset($_ENV['APP_ENV']);}else{$_ENV['APP_ENV']=$before;}}
    }
    public function testReencryptingRevokedArchivesDoesNotReactivateThem(): void
    {
        $tenant=$this->tenant();$id=F::connection($this->db,$tenant,$this->store($tenant));$this->service()->revoke($tenant,$id,2);
        $service=$this->service(new SecretCipher(new KeyRing('next',['test'=>str_repeat('t',32),'next'=>random_bytes(32)])));$service->rotate($tenant,$id,3);
        $row=$service->list($tenant)[0];self::assertSame('revoked',$row['status']);self::assertSame('next',$row['keyId']);self::assertSame(4,$row['version']);
        $this->expectException(Conflict::class);$service->rotate($tenant,$id,4,new Secrets(['apiToken'=>'cannot-reactivate']));
    }
}
