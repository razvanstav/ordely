<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Invoicing\Infrastructure\{OrderDrafts,OrderPreparations,PreparationCipher,InvoiceProfiles};
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,IntegrationFixtures,PreparationFixtures as F,ProcessHarness};
final class OrderDraftConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testConcurrentSaveAndRefreshHaveOneRevisionAndInventoryIncludesChunks(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$cipher=IntegrationFixtures::cipher();$order=F::persist($this->db,$actor,$store,$cipher);
        $command=['invoice-order-save',$actor->merchantId,$actor->membershipId,$actor->userId,$store,$order,'0','3'];
        foreach(ProcessHarness::together([$command,$command]) as $r){self::assertSame(0,$r['exit'],$r['error']);self::assertSame('1',trim($r['output']));}
        $this->db->run('UPDATE commerce_records SET version=4 WHERE id=?',[Id::bytes($order)]);$command[6]='1';$command[7]='4';
        foreach(ProcessHarness::together([$command,$command]) as $r){self::assertSame(0,$r['exit'],$r['error']);self::assertSame('2',trim($r['output']));}
        $command[0]='invoice-order-complete';$command[6]='2';
        foreach(ProcessHarness::together([$command,$command]) as $r){self::assertSame(0,$r['exit'],$r['error']);self::assertSame('3',trim($r['output']));}
        self::assertSame(3,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        $repo=new OrderDrafts($this->db,new OrderPreparations($this->db,new OrderCipher($cipher),new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher)),new PreparationCipher($cipher));self::assertSame(3,$repo->get($actor,$store,$order)['version']??null);
        $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/key-status.php','--test'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));if(!is_resource($process)){self::fail('Inventory unavailable.');}
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error===false?'':$error);
        $data=json_decode($output===false?'':$output,true,flags:JSON_THROW_ON_ERROR);self::assertGreaterThanOrEqual(1,(int)array_column($data['invoicePreparationKeyUsage'],'chunks','key_id')['test']);
    }
    public function testEnclosingFailureRollsBackDraftAndAudit(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$cipher=IntegrationFixtures::cipher();$order=F::persist($this->db,$actor,$store,$cipher);
        $repo=new OrderDrafts($this->db,new OrderPreparations($this->db,new OrderCipher($cipher),new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher)),new PreparationCipher($cipher));
        try{$this->db->transaction(function()use($repo,$actor,$store,$order):void{$repo->save($actor,$store,$order,0,3,0);throw new \RuntimeException('Synthetic transaction failure.');});}catch(\RuntimeException $e){self::assertSame('Synthetic transaction failure.',$e->getMessage());}
        self::assertNull($repo->get($actor,$store,$order));self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame(1,$repo->save($actor,$store,$order,0,3,0));
        try{$this->db->transaction(function()use($repo,$actor,$store,$order):void{$repo->complete($actor,$store,$order,1,F::fiscalDetails());throw new \RuntimeException('Synthetic completion rollback.');});}catch(\RuntimeException $e){self::assertSame('Synthetic completion rollback.',$e->getMessage());}
        $reopened=new OrderDrafts($this->db,new OrderPreparations($this->db,new OrderCipher($cipher),new InvoiceProfiles($this->db,new ProviderRegistry(),$cipher)),new PreparationCipher($cipher));
        $draft=$reopened->get($actor,$store,$order);self::assertSame(1,$draft['version']??null);self::assertArrayNotHasKey('fiscalDetails',$draft['snapshot']??[]);
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_preparation_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
    }
}
