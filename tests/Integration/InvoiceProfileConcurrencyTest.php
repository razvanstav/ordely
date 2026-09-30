<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId};
use Ordely\Integrations\Infrastructure\{Connections,KeyRing,SecretCipher};
use Ordely\Invoicing\Infrastructure\InvoiceProfiles;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{CommittedDatabaseTestCase,IntegrationFixtures as I,OblioFixtures as F,ProcessHarness};

final class InvoiceProfileConcurrencyTest extends CommittedDatabaseTestCase
{
    public function testTwoProcessesCreateOnceThenConflictingSelectionsCannotOverwrite(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$service=new Connections($this->db,F::profileRegistry(),I::cipher());$id=$service->create($actor,Id::new(),'oblio','Synthetic profile',F::credentials());$service->bind($actor,$id,1,$store);
        $command=['invoice-profile',$actor->merchantId,$actor->membershipId,$actor->userId,$store,$id,'0','TEST'];
        foreach(ProcessHarness::together([$command,$command]) as $result){self::assertSame(0,$result['exit'],$result['error']);self::assertSame('1',trim($result['output']));}
        self::assertSame(1,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_profile_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        $command[6]='1';$other=$command;$other[7]='NEXT';$outcomes=[];
        foreach(ProcessHarness::together([$command,$other]) as $result){self::assertSame(0,$result['exit'],$result['error']);$outcomes[]=trim($result['output']);}sort($outcomes);self::assertSame(['2','conflict'],$outcomes);
        $profile=(new InvoiceProfiles($this->db,F::profileRegistry(),I::cipher()))->get($actor,$store);self::assertNotNull($profile);self::assertSame(2,$profile['version']);self::assertContains($profile['series'],['TEST','NEXT']);
        self::assertSame(2,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_profile_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
    }
    public function testFailedTransactionRollsBackProfileAndAuditAndKeyInventoryIncludesSelection(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$key='profile-'.Id::new();$cipher=new SecretCipher(new KeyRing($key,[$key=>random_bytes(32)]));
        $service=new Connections($this->db,F::profileRegistry(),$cipher);$id=$service->create($actor,Id::new(),'oblio','Synthetic inventory',F::credentials());$service->bind($actor,$id,1,$store);
        $profiles=new InvoiceProfiles($this->db,F::profileRegistry(),$cipher);$context=new ConnectionContext(new MerchantId($actor->merchantId),new StoreId($store),new ConnectionId($id),CorrelationId::new());
        try{
            $this->db->transaction(function()use($profiles,$actor,$context):void{
                $profiles->save($actor,$context,2,0,'TEST001','TEST');
                throw new \RuntimeException('Synthetic enclosing transaction failure.');
            });
        }catch(\RuntimeException $error){self::assertSame('Synthetic enclosing transaction failure.',$error->getMessage());}
        self::assertNull($profiles->get($actor,$store));
        self::assertSame(0,(int)$this->db->run("SELECT COUNT(*) FROM audit_logs WHERE merchant_id=? AND action='invoice_profile_saved'",[Id::bytes($actor->merchantId)])->fetchColumn());
        self::assertSame(1,$profiles->save($actor,$context,2,0,'TEST001','TEST'));
        $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/key-status.php','--test'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2));
        if(!is_resource($process)){self::fail('Key inventory unavailable.');}
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error===false?'':$error);
        $data=json_decode($output===false?'':$output,true,flags:JSON_THROW_ON_ERROR);$usage=array_column($data['invoiceProfileKeyUsage'],'profiles','key_id');self::assertSame(1,(int)$usage[$key]);
    }
}
