<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;
use Ordely\Identity\Domain\Role;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Http\Application;
use Ordely\Shared\Id;
use Ordely\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\{Request,Response};

final class IntegrationsHttpTest extends DatabaseTestCase
{
    private string $keyFile;
    private mixed $previousKeyFile;
    protected function setUp(): void
    {
        parent::setUp();$this->keyFile=dirname(__DIR__,2).'/var/http-key-'.Id::new().'.json';
        file_put_contents($this->keyFile,json_encode(['active'=>'test','keys'=>['test'=>base64_encode(random_bytes(32))]],JSON_THROW_ON_ERROR));
        $this->previousKeyFile=$_ENV['ORDELY_KEYRING_FILE']??null;$_ENV['ORDELY_KEYRING_FILE']=$this->keyFile;
    }
    protected function tearDown(): void
    {
        if($this->previousKeyFile===null){unset($_ENV['ORDELY_KEYRING_FILE']);}else{$_ENV['ORDELY_KEYRING_FILE']=$this->previousKeyFile;}
        if(is_file($this->keyFile)){unlink($this->keyFile);}parent::tearDown();
    }
    /** @param array<string,mixed> $body */
    private function request(string $path,string $token,string $method='GET',array $body=[],bool $csrf=true): Response
    {
        $server=['CONTENT_TYPE'=>'application/json'];if($csrf){$server['HTTP_X_CSRF_TOKEN']=Sessions::csrf($token);}
        return (new Application(fn():\PDO=>$this->db->pdo))->handle(Request::create('https://localhost'.$path,$method,[],['ordely_session'=>$token],[],$server,json_encode((object)$body,JSON_THROW_ON_ERROR)));
    }
    public function testConnectionLifecycleAndResponsesNeverRevealStoredSecret(): void
    {
        $actor=$this->tenant();$store=$this->store($actor);$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$id=Id::new();
        $body=['id'=>$id,'provider'=>'fake-carrier','label'=>'HTTP test','credentials'=>['apiToken'=>'SYNTHETIC-HTTP-CREDENTIAL']];
        $first=$this->request('/api/integrations',$token,'POST',$body);self::assertSame(201,$first->getStatusCode());
        self::assertSame($first->getContent(),$this->request('/api/integrations',$token,'POST',$body)->getContent());
        $list=$this->request('/api/integrations',$token);self::assertSame(200,$list->getStatusCode());self::assertStringNotContainsString('SYNTHETIC-HTTP-CREDENTIAL',(string)$list->getContent());self::assertStringNotContainsString('credentials_envelope',(string)$list->getContent());
        self::assertSame(403,$this->request('/api/integrations/'.$id.'/rotate',$token,'POST',['version'=>1],false)->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/bind',$token,'POST',['version'=>1,'storeId'=>$store,'isDefault'=>true])->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/capabilities',$token,'POST',['storeId'=>$store,'kind'=>'carrier'])->getStatusCode());
        self::assertSame(403,$this->request('/api/integrations/'.$id.'/capabilities',$token,'POST',['storeId'=>$store,'kind'=>'invoice'])->getStatusCode());
        self::assertSame(409,$this->request('/api/integrations/'.$id.'/rotate',$token,'POST',['version'=>1])->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/credentials',$token,'POST',['version'=>2,'credentials'=>['apiToken'=>'REPLACEMENT-HTTP-TOKEN']])->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/rotate',$token,'POST',['version'=>3])->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/revoke',$token,'POST',['version'=>4])->getStatusCode());
        self::assertSame(403,$this->request('/api/integrations/'.$id.'/capabilities',$token,'POST',['storeId'=>$store,'kind'=>'carrier'])->getStatusCode());
        self::assertSame(200,$this->request('/api/integrations/'.$id.'/rotate',$token,'POST',['version'=>5])->getStatusCode());
        self::assertSame(403,$this->request('/api/integrations/'.$id.'/capabilities',$token,'POST',['storeId'=>$store,'kind'=>'carrier'])->getStatusCode());
    }
    public function testRolesTenantAndCurrentMembershipAreCheckedAtHttpBoundary(): void
    {
        $actor=$this->tenant();$foreign=$this->tenant();$sessions=new Sessions($this->db);$token=$sessions->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');$other=$sessions->login($foreign->userId.'@example.test',self::PASSWORD,'127.0.0.1');$id=Id::new();
        self::assertSame(201,$this->request('/api/integrations',$token,'POST',['id'=>$id,'provider'=>'fake-commerce','label'=>'Store','credentials'=>['apiToken'=>'synthetic']])->getStatusCode());
        self::assertStringNotContainsString($id,(string)$this->request('/api/integrations',$other)->getContent());
        self::assertSame(403,$this->request('/api/integrations/'.$id.'/revoke',$other,'POST',['version'=>1])->getStatusCode());
        foreach([[Role::Admin,false],[Role::Operator,true],[Role::Finance,true],[Role::Viewer,true]] as [$role,$all]){
            $tenant=$this->tenant($role,$all);$limited=$sessions->login($tenant->userId.'@example.test',self::PASSWORD,'127.0.0.1');self::assertSame(403,$this->request('/api/integrations',$limited)->getStatusCode());
        }
        $this->db->run("UPDATE memberships SET status='disabled' WHERE id=?",[Id::bytes($actor->membershipId)]);
        self::assertSame(401,$this->request('/api/integrations',$token)->getStatusCode());
    }
    public function testUnavailableProvidersAndUnrecognizedCredentialFieldsAreRejected(): void
    {
        $actor=$this->tenant();$token=(new Sessions($this->db))->login($actor->userId.'@example.test',self::PASSWORD,'127.0.0.1');
        foreach([['provider'=>'shopify','credentials'=>['apiToken'=>'fake']],['provider'=>'fake-carrier','credentials'=>['apiToken'=>'fake','unexpected'=>'do-not-store']]] as $body){
            self::assertSame(400,$this->request('/api/integrations',$token,'POST',['id'=>Id::new(),'label'=>'Rejected',...$body])->getStatusCode());
        }
        self::assertSame(0,(int)$this->db->run('SELECT COUNT(*) FROM provider_connections WHERE merchant_id=?',[Id::bytes($actor->merchantId)])->fetchColumn());
    }
}
