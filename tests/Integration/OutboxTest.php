<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Infrastructure\StoreRepository;
use Ordely\Operations\Domain\{EventType,SafePayload,Scope};
use Ordely\Operations\Infrastructure\Outbox;
use Ordely\Shared\Id;
use Ordely\Tests\Support\DatabaseTestCase;

final class OutboxTest extends DatabaseTestCase
{
    public function testStoreChangeAuditAndOutboxCommitOrRollBackTogether(): void
    {
        $tenant=$this->tenant();$stores=new StoreRepository($this->db);
        $store=$stores->create($tenant,'Private merchant label','manual');
        self::assertTrue($stores->rename($tenant,$store,'Another private label'));
        $params=[Id::bytes($tenant->merchantId)];
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM audit_logs WHERE merchant_id=?',$params)->fetchColumn());
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM outbox_events WHERE merchant_id=?',$params)->fetchColumn());
        self::assertSame(2,(int)$this->db->run('SELECT version FROM stores WHERE id=?',[Id::bytes($store)])->fetchColumn());
        $payload=(string)$this->db->run('SELECT payload FROM outbox_events WHERE merchant_id=? LIMIT 1',$params)->fetchColumn();
        self::assertStringNotContainsString('Private',$payload);self::assertStringNotContainsString('label',$payload);
        try{$this->db->transaction(function()use($stores,$tenant):void{$stores->create($tenant,'Rolled back','manual');throw new \RuntimeException('simulate rollback');});}catch(\RuntimeException){self::assertCount(1,$stores->list($tenant));}
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM outbox_events WHERE merchant_id=?',$params)->fetchColumn());
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM audit_logs WHERE merchant_id=?',$params)->fetchColumn());
    }
    public function testCrossTenantScopeCannotAppendAnEvent(): void
    {
        $a=$this->tenant();$b=$this->tenant();$store=$this->store($b);
        $this->expectException(\Ordely\Identity\Domain\AccessDenied::class);
        (new Outbox($this->db))->append(new Scope($a->merchantId,$store),EventType::StoreCreated,$store,1,new SafePayload());
    }
    public function testSensitivePayloadsAndMalformedReferencesAreRejected(): void
    {
        foreach([['password'=>'secret'],['email'=>'user@example.test'],['iban'=>'RO00TEST'],['store_id'=>'secret'],['count'=>-1]] as $data){
            try{new SafePayload($data);self::fail('Expected allowlist rejection.');}catch(\InvalidArgumentException){self::addToAssertionCount(1);}
        }
        $id=Id::new();self::assertSame($id,SafePayload::fromJson((new SafePayload(['store_id'=>$id]))->json())->id('store_id'));
    }
}
