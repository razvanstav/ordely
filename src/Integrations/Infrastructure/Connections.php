<?php
declare(strict_types=1);
namespace Ordely\Integrations\Infrastructure;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

final readonly class Connections
{
    public function __construct(private Sql $db,private ProviderRegistry $registry,private ?SecretCipher $cipher=null) {}
    public function manage(TenantContext $actor): void
    {
        (new AccessPolicy($this->db))->require($actor,'connections.manage');
        $row=$this->db->one('SELECT all_stores FROM memberships WHERE merchant_id=? AND id=?'.($this->db->pdo->inTransaction()?' FOR SHARE':''),[Id::bytes($actor->merchantId),Id::bytes($actor->membershipId)]);
        if($row===null||!(bool)$row['all_stores']){throw new AccessDenied('forbidden');}
    }
    /** @return list<array<string,mixed>> */
    public function list(TenantContext $actor): array
    {
        return $this->db->transaction(function()use($actor):array{
            $this->manage($actor);
            $rows=$this->db->run("SELECT LOWER(HEX(id)) id,provider_key provider,kind,label,status,version,JSON_UNQUOTE(JSON_EXTRACT(credentials_envelope,'$.key')) keyId FROM provider_connections WHERE merchant_id=? ORDER BY created_at,id",[Id::bytes($actor->merchantId)])->fetchAll(\PDO::FETCH_ASSOC);
            foreach($rows as &$row){
                $row['version']=(int)$row['version'];
                $row['bindings']=$this->db->run('SELECT LOWER(HEX(store_id)) storeId,is_default isDefault FROM store_provider_bindings WHERE merchant_id=? AND connection_id=? ORDER BY store_id',[Id::bytes($actor->merchantId),Id::bytes((string)$row['id'])])->fetchAll(\PDO::FETCH_ASSOC);
            }unset($row);return array_values($rows);
        });
    }
    /** Client-chosen random ID makes a retried submission return the same connection. */
    public function create(TenantContext $actor,string $id,string $provider,string $label,Secrets $credentials): string
    {
        Id::bytes($id);$label=trim($label);
        if($label===''||mb_strlen($label)>160||preg_match('/[\x00-\x1f]/',$label)){throw new \InvalidArgumentException('Invalid label.');}
        $definition=$this->registry->get($provider);$definition->validate($credentials);
        return $this->db->transaction(function()use($actor,$id,$provider,$label,$credentials,$definition):string{
            $this->manage($actor);$cipher=$this->cipher();
            $envelope=$cipher->encrypt($actor->merchantId,$id,$provider,$credentials);
            $this->db->run('INSERT INTO provider_connections(id,merchant_id,kind,provider_key,label,credentials_envelope) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',[Id::bytes($id),Id::bytes($actor->merchantId),$definition->kind->value,$provider,$label,$envelope]);
            $row=$this->db->one('SELECT * FROM provider_connections WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($id)]);
            if($row===null||$row['provider_key']!==$provider||$row['label']!==$label||$row['status']!=='active'){throw new Conflict('connection_conflict');}
            $stored=$cipher->decrypt($actor->merchantId,$id,$provider,(string)$row['credentials_envelope']);
            $a=$stored->reveal();$b=$credentials->reveal();ksort($a);ksort($b);
            if(!hash_equals(json_encode($a,JSON_THROW_ON_ERROR),json_encode($b,JSON_THROW_ON_ERROR))){throw new Conflict('connection_conflict');}
            // The ciphertext equals the freshly generated envelope only for this insertion.
            $fresh=json_decode((string)$row['credentials_envelope'],true,flags:JSON_THROW_ON_ERROR)==json_decode($envelope,true,flags:JSON_THROW_ON_ERROR);
            if($fresh){$this->audit($actor,$id,AuditAction::ConnectionCreated,1);}return $id;
        });
    }
    public function rotate(TenantContext $actor,string $id,int $version,?Secrets $replacement=null): void
    {
        $this->db->transaction(function()use($actor,$id,$version,$replacement):void{
            $row=$this->locked($actor,$id,$version,allowRevoked:$replacement===null);$provider=(string)$row['provider_key'];$cipher=$this->cipher();
            $credentials=$replacement??$cipher->decrypt($actor->merchantId,$id,$provider,(string)$row['credentials_envelope']);
            if($replacement!==null){$this->registry->get($provider)->validate($credentials);}
            $envelope=$cipher->encrypt($actor->merchantId,$id,$provider,$credentials);
            $this->db->run('UPDATE provider_connections SET credentials_envelope=?,version=version+1 WHERE merchant_id=? AND id=?',[$envelope,Id::bytes($actor->merchantId),Id::bytes($id)]);
            $this->audit($actor,$id,$replacement===null?AuditAction::ConnectionReencrypted:AuditAction::CredentialsReplaced,$version+1);
        });
    }
    public function revoke(TenantContext $actor,string $id,int $version): void
    {
        $this->db->transaction(function()use($actor,$id,$version):void{
            $this->locked($actor,$id,$version);
            $this->db->run("UPDATE provider_connections SET status='revoked',revoked_at=UTC_TIMESTAMP(6),version=version+1 WHERE merchant_id=? AND id=?",[Id::bytes($actor->merchantId),Id::bytes($id)]);
            $this->audit($actor,$id,AuditAction::ConnectionRevoked,$version+1);
        });
    }
    public function bind(TenantContext $actor,string $id,int $version,string $store,bool $default=false,bool $remove=false): void
    {
        $this->db->transaction(function()use($actor,$id,$version,$store,$default,$remove):void{
            $this->manage($actor);(new AccessPolicy($this->db))->require($actor,'connections.manage',$store);
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $row=$this->locked($actor,$id,$version);$params=[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)];
            if($remove){$this->db->run('DELETE FROM store_provider_bindings WHERE merchant_id=? AND store_id=? AND connection_id=?',$params);}
            else{
                if($default){$this->db->run('UPDATE store_provider_bindings SET is_default=0 WHERE merchant_id=? AND store_id=? AND kind=?',[$params[0],$params[1],(string)$row['kind']]);}
                $this->db->run('INSERT INTO store_provider_bindings(merchant_id,store_id,connection_id,kind,is_default) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE is_default=?',[...$params,(string)$row['kind'],(int)$default,(int)$default]);
            }
            $this->db->run('UPDATE provider_connections SET version=version+1 WHERE merchant_id=? AND id=?',[$params[0],$params[2]]);
            $this->audit($actor,$id,$remove?AuditAction::ConnectionUnbound:AuditAction::ConnectionBound,$version+1,$store);
        });
    }
    /** @return array<string,mixed> */
    private function locked(TenantContext $actor,string $id,int $version,bool $allowRevoked=false): array
    {
        $this->manage($actor);
        $row=$this->db->one('SELECT * FROM provider_connections WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($id)]);
        if($row===null){throw new AccessDenied('forbidden');}
        if(($row['status']!=='active'&&!$allowRevoked)||$version<1||(int)$row['version']!==$version){throw new Conflict('connection_version_conflict');}return $row;
    }
    private function cipher(): SecretCipher { return $this->cipher??throw new \RuntimeException('Keyring unavailable.'); }
    private function audit(TenantContext $actor,string $id,AuditAction $action,int $version,?string $store=null): void
    {
        (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),$action,$id,new SafePayload(['connection_id'=>$id,'version'=>$version]),Id::new());
    }
}
