<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Identity\Domain\{AccessDenied,Role,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Infrastructure\{Connections,ConnectionGuard,SecretCipher};
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

final readonly class Installations
{
    public function __construct(private Sql $db,private AppConfig $config,private SecretCipher $cipher,private ShopifyGateway $gateway) {}

    public function manage(TenantContext $actor,?string $store=null): void
    {
        /** @var ProviderRegistry $registry */
        $registry=require dirname(__DIR__,3).'/config/providers.php';
        (new Connections($this->db,$registry))->manage($actor);
        if($store!==null){(new AccessPolicy($this->db))->require($actor,'connections.manage',$store);}
    }

    /** One-use bearer proof of the Ordely owner's authorization; never stored in plaintext.
     * @return array{code:string,shop:string,expiresIn:int} */
    public function intent(TenantContext $actor,string $store,ShopDomain $shop): array
    {
        $this->config->allow($shop);
        return $this->db->transaction(function()use($actor,$store,$shop):array{
            $this->manage($actor,$store);
            $record=$this->db->one('SELECT platform_key FROM stores WHERE merchant_id=? AND id=? FOR SHARE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            if($record===null||$record['platform_key']!=='shopify'){throw new \InvalidArgumentException('A Shopify store is required.');}
            $code=bin2hex(random_bytes(32));
            $this->db->run('INSERT INTO shopify_link_intents(code_hash,merchant_id,membership_id,user_id,store_id,shop_domain,expires_at) VALUES(?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE))',[
                hash('sha256',$code,true),Id::bytes($actor->merchantId),Id::bytes($actor->membershipId),Id::bytes($actor->userId),Id::bytes($store),$shop->value,
            ]);
            return ['code'=>$code,'shop'=>$shop->value,'expiresIn'=>600];
        });
    }

    public function connect(#[\SensitiveParameter] string $idToken,#[\SensitiveParameter] string $code): string
    {
        $identity=(new IdTokenVerifier($this->config))->verify($idToken);
        if(!preg_match('/^[a-f0-9]{64}$/D',$code)){throw new AccessDenied('invalid_link_code');}
        return $this->db->transaction(function()use($identity,$idToken,$code):string{
            $intent=$this->db->one('SELECT * FROM shopify_link_intents WHERE code_hash=? AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE',[hash('sha256',$code,true)]);
            if($intent===null||$intent['shop_domain']!==$identity->shop->value){throw new AccessDenied('invalid_link_code');}
            $membership=$this->db->one('SELECT role,all_stores FROM memberships WHERE merchant_id=? AND id=? FOR SHARE',[(string)$intent['merchant_id'],(string)$intent['membership_id']]);
            if($membership===null){throw new AccessDenied('forbidden');}
            $actor=new TenantContext(bin2hex((string)$intent['merchant_id']),bin2hex((string)$intent['membership_id']),bin2hex((string)$intent['user_id']),Role::from((string)$membership['role']),(bool)$membership['all_stores']);
            $store=bin2hex((string)$intent['store_id']);$this->manage($actor,$store);
            // The unique shop and store keys serialize exchange/refresh and prevent tenant takeover.
            $this->db->run('INSERT INTO shopify_links(shop_domain,merchant_id,store_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE shop_domain=shop_domain',[$identity->shop->value,Id::bytes($actor->merchantId),Id::bytes($store)]);
            $link=$this->db->one('SELECT * FROM shopify_links WHERE shop_domain=? FOR UPDATE',[$identity->shop->value]);
            if($link===null||$link['merchant_id']!==$intent['merchant_id']||$link['store_id']!==$intent['store_id']){throw new AccessDenied('shop_already_linked');}
            if($intent['connection_id']!==null){
                if($intent['connection_id']!==$link['connection_id']||$this->db->one("SELECT id FROM provider_connections WHERE id=? AND status='active'",[(string)$intent['connection_id']])===null){throw new Conflict('link_code_used');}
                return bin2hex((string)$intent['connection_id']);
            }
            $started=(int)floor(microtime(true)*1000000);$tokens=$this->gateway->exchange($identity->shop,$idToken);
            $externalShop=$this->gateway->probe($identity->shop,$tokens->secrets->reveal()['accessToken']);
            if($link['connection_id']!==null){$this->revoke($actor->merchantId,$store,bin2hex((string)$link['connection_id']));}
            $id=Id::new();$envelope=$this->cipher->encrypt($actor->merchantId,$id,'shopify',$tokens->stored());
            $this->db->run("INSERT INTO provider_connections(id,merchant_id,kind,provider_key,label,credentials_envelope) VALUES(?,?,'commerce','shopify',?,?)",[Id::bytes($id),Id::bytes($actor->merchantId),'Shopify · '.$identity->shop->value,$envelope]);
            $this->db->run("UPDATE store_provider_bindings SET is_default=0 WHERE merchant_id=? AND store_id=? AND kind='commerce'",[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $this->db->run("INSERT INTO store_provider_bindings(merchant_id,store_id,connection_id,kind,is_default) VALUES(?,?,?,'commerce',1)",[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)]);
            $this->db->run('UPDATE shopify_links SET connection_id=?,installed_at=?,external_shop_id=? WHERE shop_domain=?',[Id::bytes($id),$started,$externalShop,$identity->shop->value]);
            $this->db->run('UPDATE shopify_link_intents SET connection_id=? WHERE code_hash=?',[Id::bytes($id),hash('sha256',$code,true)]);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::IntegrationLinked,$id,new SafePayload(['connection_id'=>$id]));
            return $id;
        });
    }

    /** Execute while holding the installation/connection locks; no reusable authenticated adapter escapes.
     * @template T
     * @param \Closure(ShopDomain,string):T $work
     * @return T */
    public function withAccess(ConnectionContext $context,\Closure $work,bool $forceRefresh=false): mixed
    {
        $outcome=$this->db->transaction(function()use($context,$work,$forceRefresh):array{
            $merchant=$context->merchant->value;$store=$context->store->value;$connection=$context->connection->value;
            $link=$this->db->one('SELECT * FROM shopify_links WHERE merchant_id=? AND store_id=? AND connection_id=? FOR UPDATE',[Id::bytes($merchant),Id::bytes($store),Id::bytes($connection)]);
            if($link===null){throw new AccessDenied('connection_unavailable');}
            $this->db->one('SELECT id FROM provider_connections WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($merchant),Id::bytes($connection)]);
            $row=(new ConnectionGuard($this->db))->active($context);
            if($row['provider_key']!=='shopify'){throw new AccessDenied('connection_unavailable');}
            $shop=new ShopDomain((string)$link['shop_domain']);$this->config->allow($shop);
            $tokens=TokenSet::fromStored($this->cipher->decrypt($merchant,$connection,'shopify',(string)$row['credentials_envelope']));
            try{
                if($forceRefresh||$tokens->expiresAt<=time()+60){
                    if($tokens->refreshExpiresAt<=time()){throw new AuthorizationLost();}
                    $tokens=$this->gateway->refresh($shop,$tokens->secrets->reveal()['refreshToken']);
                    $this->db->run('UPDATE provider_connections SET credentials_envelope=?,version=version+1 WHERE merchant_id=? AND id=?',[$this->cipher->encrypt($merchant,$connection,'shopify',$tokens->stored()),Id::bytes($merchant),Id::bytes($connection)]);
                    (new AuditLog($this->db))->append(new Scope($merchant,$store),Actor::system(),AuditAction::IntegrationTokenRefreshed,$connection,new SafePayload(['connection_id'=>$connection]));
                }
                return ['ok'=>true,'value'=>$work($shop,$tokens->secrets->reveal()['accessToken'])];
            }catch(AuthorizationLost){$this->revoke($merchant,$store,$connection);return ['ok'=>false];}
            catch(\Throwable $error){return ['ok'=>true,'error'=>$error];}
        });
        // Commit revocation before reporting it; throwing inside the transaction would undo it.
        if(!$outcome['ok']){throw new AuthorizationLost();}
        // A refreshed token pair must survive a subsequent API timeout or throttling response.
        if(isset($outcome['error'])){throw $outcome['error'];}return $outcome['value'];
    }

    public function check(ConnectionContext $context,bool $refresh=false): void
    {
        $this->withAccess($context,function(ShopDomain $shop,#[\SensitiveParameter] string $token):null{$this->gateway->probe($shop,$token);return null;},$refresh);
    }

    /** Lifecycle ingress also calls this for disabled tenants; revocation must remain possible. */
    public function revoke(string $merchant,string $store,string $connection): void
    {
        $changed=$this->db->run("UPDATE provider_connections SET status='revoked',revoked_at=UTC_TIMESTAMP(6),version=version+1 WHERE merchant_id=? AND id=? AND status='active'",[Id::bytes($merchant),Id::bytes($connection)])->rowCount();
        if($changed){(new AuditLog($this->db))->append(new Scope($merchant,$store),Actor::system(),AuditAction::ConnectionRevoked,$connection,new SafePayload(['connection_id'=>$connection]));}
    }

    /** @return array{shop:string,connected:bool} */
    public function status(ShopIdentity $identity): array
    {
        $row=$this->db->one("SELECT c.id FROM shopify_links l JOIN provider_connections c ON c.merchant_id=l.merchant_id AND c.id=l.connection_id JOIN stores s ON s.merchant_id=l.merchant_id AND s.id=l.store_id JOIN merchants m ON m.id=l.merchant_id WHERE l.shop_domain=? AND c.status='active' AND s.status='active' AND m.status='active'",[$identity->shop->value]);
        return ['shop'=>$identity->shop->value,'connected'=>$row!==null];
    }
}
