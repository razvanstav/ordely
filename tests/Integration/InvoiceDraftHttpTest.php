<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Identity\Domain\Role;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Shared\Id;
use Ordely\Tests\Support\{DatabaseTestCase,InvoiceFixtures as F};
use Symfony\Component\HttpFoundation\{Request,Response};

final class InvoiceDraftHttpTest extends DatabaseTestCase
{
    private string $keyFile;
    private mixed $previousKeyFile;
    protected function setUp(): void
    {
        parent::setUp();$this->keyFile=dirname(__DIR__,2).'/var/invoice-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        $this->previousKeyFile=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->keyFile;
    }
    protected function tearDown(): void
    {
        if($this->previousKeyFile===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previousKeyFile;}
        if(is_file($this->keyFile)){unlink($this->keyFile);}parent::tearDown();
    }
    /** @param array<string,mixed> $body */
    private function request(string $path,string $token,string $method='GET',array $body=[],bool $csrf=true,?string $origin=null): Response
    {
        $server=['CONTENT_TYPE'=>'application/json'];if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($token);}if($origin!==null){$server['HTTP_ORIGIN']=$origin;}
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost'.$path,$method,[],['ordely_session'=>$token],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    public function testLifecycleValidatesTotalsVersionsAndArchives(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$id=Id::new();
        $body=['id'=>$id,'storeId'=>$store,'document'=>F::data()];$path='/api/invoice-drafts/'.$id;
        $preview=$this->request('/api/invoice-drafts/preview',$token,'POST',$body);self::assertSame(200,$preview->getStatusCode());self::assertStringContainsString('"total":"23.00"',(string)$preview->getContent());
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_drafts WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
        foreach([1,2] as $_){self::assertSame(201,$this->request('/api/invoice-drafts',$token,'POST',$body)->getStatusCode());}
        $get=$this->request($path.'?storeId='.$store,$token);self::assertSame(200,$get->getStatusCode());self::assertStringContainsString('no-store',(string)$get->headers->get('Cache-Control'));self::assertStringNotContainsString('document_envelope',(string)$get->getContent());
        $body['version']=1;$body['document']['lines'][0]['tax']='4.00';
        self::assertSame(200,$this->request($path,$token,'PUT',$body)->getStatusCode());self::assertSame(409,$this->request($path,$token,'PUT',$body)->getStatusCode());
        self::assertSame(200,$this->request($path.'/archive',$token,'POST',['storeId'=>$store,'version'=>2])->getStatusCode());
        self::assertSame(409,$this->request($path,$token,'PUT',[...$body,'version'=>3])->getStatusCode());
        $archived=$this->request('/api/invoice-drafts?storeId='.$store.'&status=ARCHIVED',$token);self::assertSame(200,$archived->getStatusCode());self::assertStringContainsString($id,(string)$archived->getContent());self::assertStringNotContainsString('SYNTHETIC-PRIVATE-RECIPIENT',(string)$archived->getContent());
        $active=$this->request('/api/invoice-drafts?storeId='.$store,$token);self::assertStringNotContainsString($id,(string)$active->getContent());
    }
    public function testCsrfOriginRolesAndTenantIsolationAreEnforcedWithoutShopify(): void
    {
        $actor=$this->tenant();$foreign=$this->tenant();$store=$this->store($actor);$otherStore=$this->store($foreign);$sessions=new Sessions($this->db);
        $token=$sessions->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$other=$sessions->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');$id=Id::new();$path='/api/invoice-drafts/'.$id;
        $body=['id'=>$id,'storeId'=>$store,'document'=>F::data()];
        self::assertSame(401,$this->request('/api/invoice-drafts','')->getStatusCode());
        self::assertSame(403,$this->request('/api/invoice-drafts',$token,'POST',$body,false)->getStatusCode());
        self::assertSame(403,$this->request('/api/invoice-drafts',$token,'POST',$body,true,'https://foreign.test')->getStatusCode());
        self::assertSame(201,$this->request('/api/invoice-drafts',$token,'POST',$body)->getStatusCode());
        foreach([$store,$otherStore] as $scope){self::assertSame(403,$this->request($path.'?storeId='.$scope,$other)->getStatusCode());}
        self::assertSame(403,$this->request($path,$other,'PUT',[...$body,'version'=>1])->getStatusCode());
        foreach([Role::Finance,Role::Admin,Role::Operator,Role::Viewer] as $role){
            $this->db->run('UPDATE memberships SET role=? WHERE id=?',[$role->value,Id::bytes($actor->membershipId)]);
            self::assertSame($role===Role::Viewer?403:200,$this->request($path.'?storeId='.$store,$token)->getStatusCode());
            self::assertSame(in_array($role,[Role::Finance,Role::Admin],true)?200:403,$this->request('/api/invoice-drafts/preview',$token,'POST',$body)->getStatusCode());
        }
    }
    public function testMalformedDocumentsStayClientErrorsAndNeverCreateRows(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        $deep='value';for($i=0;$i<20;++$i){$deep=['nested'=>$deep];}
        foreach([[],[...F::data(),'total'=>'0.01'],[...F::data(),'customerName'=>$deep]] as $document){
            self::assertSame(400,$this->request('/api/invoice-drafts',$token,'POST',['id'=>Id::new(),'storeId'=>$store,'document'=>$document])->getStatusCode());
        }
        self::assertSame(413,$this->request('/api/invoice-drafts',$token,'POST',['id'=>Id::new(),'storeId'=>$store,'document'=>[...F::data(),'customerName'=>str_repeat('x',17000)]])->getStatusCode());
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM invoice_drafts WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
}
