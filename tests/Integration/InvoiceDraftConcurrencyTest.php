<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Invoicing\Infrastructure\DraftRepository;
use Ordely\Invoicing\Infrastructure\DraftCipher;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,InvoiceFixtures as F,ProcessHarness};

final class InvoiceDraftConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testCliKeyInventoryIncludesOldRevisionsOfArchivedDrafts(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=Id::new();$old='old-'.Id::new();$next='next-'.Id::new();$keys=[$old=>random_bytes(32),$next=>random_bytes(32)];
        $before=new DraftRepository($this->db,new DraftCipher(new SecretCipher(new KeyRing($old,$keys))));$before->create($actor,$store,$id,F::document());
        $after=new DraftRepository($this->db,new DraftCipher(new SecretCipher(new KeyRing($next,$keys))));$after->replace($actor,$store,$id,1,F::document());$after->archive($actor,$store,$id,2);
        self::assertSame('ARCHIVED',$after->get($actor,$store,$id)['status']);
        $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/key-status.php','--test'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));
        if(!is_resource($process)){self::fail('Key inventory process unavailable.');}
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error===false?'':$error);
        $data=json_decode($output===false?'':$output,true,flags:JSON_THROW_ON_ERROR);$usage=array_column($data['invoiceDraftKeyUsage'],'revisions','key_id');
        self::assertSame(1,(int)$usage[$old]);self::assertSame(2,(int)$usage[$next]);self::assertNotContains($old,array_column($data['keyUsage'],'key_id'));
    }
    public function testConcurrentCreationReturnsOneDraftAndOneBusinessEvent(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=Id::new();$command=['invoice-create',$actor->merchantId,$actor->membershipId,$actor->userId,$store,$id];
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);self::assertSame($id,trim($result['output']));}
        foreach(['invoice_drafts','invoice_draft_revisions','audit_logs','outbox_events'] as $table){self::assertSame(1,(int)$this->db->run('SELECT COUNT(*) FROM '.$table.' WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());}
    }
    public function testTwoEditorsCannotOverwriteOneRevision(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$id=Id::new();$repo=new DraftRepository($this->db,F::cipher());$repo->create($actor,$store,$id,F::document());
        $command=['invoice-edit',$actor->merchantId,$actor->membershipId,$actor->userId,$store,$id];$outcomes=[];
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);$outcomes[]=trim($result['output']);}sort($outcomes);self::assertSame(['conflict','updated'],$outcomes);
        self::assertSame(2,$repo->get($actor,$store,$id)['version']);self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM invoice_draft_revisions WHERE draft_id=?',[Id::bytes($id)])->fetchColumn());
        self::assertSame(2,(int)$this->db->run('SELECT COUNT(*) FROM outbox_events WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
}
