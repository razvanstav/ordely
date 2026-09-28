<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\SecretCipher;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;
use Symfony\Component\HttpFoundation\Request;

/** Lifecycle work is short and committed with the durable inbox before acknowledgment. */
final readonly class WebhookInbox
{
    public function __construct(private Sql $db,private AppConfig $config,private SecretCipher $cipher,private Installations $installations) {}

    public function receive(Request $request): void
    {
        $raw=$request->getContent();
        if(strlen($raw)>32768){throw new Problem(413,'body_too_large');}
        if(!(new WebhookSignature($this->config))->valid($raw,$request->headers->get('X-Shopify-Hmac-SHA256')??'')){throw new Problem(401,'invalid_webhook_signature');}
        $shop=new ShopDomain($request->headers->get('X-Shopify-Shop-Domain')??'');$this->config->allow($shop);
        $topic=$request->headers->get('X-Shopify-Topic')??'';
        if(!in_array($topic,['app/uninstalled','customers/data_request','customers/redact','shop/redact'],true)){throw new Problem(400,'unsupported_topic');}
        $delivery=$request->headers->get('X-Shopify-Webhook-Id')??'';
        if(!preg_match('/^[a-zA-Z0-9_-]{8,128}$/D',$delivery)){throw new Problem(400,'invalid_delivery');}
        try{$payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(400,'invalid_json');}
        if(!is_array($payload)||array_is_list($payload)){throw new Problem(400,'object_required');}
        $triggered=$this->timestamp($request->headers->get('X-Shopify-Triggered-At')??'');
        $this->db->run('SET SESSION innodb_lock_wait_timeout=3');
        $this->db->transaction(function()use($shop,$topic,$delivery,$raw,$payload,$triggered):void{
            $link=$this->db->one('SELECT * FROM shopify_links WHERE shop_domain=? FOR UPDATE',[$shop->value]);
            // A verified delivery for a shop never linked to Ordely has no tenant data or token to revoke.
            if($link===null){return;}
            $signedShop=$topic==='app/uninstalled'?($payload['id']??null):($payload['shop_id']??null);
            $signedDomain=$topic==='app/uninstalled'?($payload['myshopify_domain']??null):($payload['shop_domain']??null);
            if((!is_int($signedShop)&&!is_string($signedShop))||(string)$signedShop!==$link['external_shop_id']
                ||($signedDomain!==null&&$signedDomain!==$shop->value)){throw new Problem(400,'webhook_shop_mismatch');}
            $hash=hash('sha256',$raw,true);$deliveryHash=hash('sha256',$delivery,true);
            $existing=$this->db->one('SELECT body_hash,topic FROM shopify_webhook_events WHERE shop_domain=? AND delivery_hash=?',[$shop->value,$deliveryHash]);
            if($existing!==null){
                if(!hash_equals((string)$existing['body_hash'],$hash)||$existing['topic']!==$topic){throw new Conflict('webhook_conflict');}return;
            }
            $merchant=bin2hex((string)$link['merchant_id']);$store=bin2hex((string)$link['store_id']);$id=Id::new();
            $chunks=[];foreach(str_split(base64_encode($raw),4096) as $index=>$chunk){$chunks['body'.$index]=$chunk;}
            $envelope=$this->cipher->encrypt($merchant,$id,'shopify-webhook',new Secrets($chunks));
            $status='needs_review';
            if($topic==='app/uninstalled'){
                if($triggered===null||$triggered>(int)floor((microtime(true)+300)*1000000)){$status='needs_review';}
                elseif($link['installed_at']!==null&&$triggered<=(int)$link['installed_at']){$status='ignored';}
                else{
                    if($link['connection_id']!==null){$this->installations->revoke($merchant,$store,bin2hex((string)$link['connection_id']));}
                    $status='processed';
                }
            }
            // Privacy requests are retained for explicit processing, never falsely marked fulfilled.
            $this->db->run('INSERT INTO shopify_webhook_events(id,merchant_id,store_id,connection_id,shop_domain,delivery_hash,body_hash,topic,payload_envelope,triggered_at,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[
                Id::bytes($id),(string)$link['merchant_id'],(string)$link['store_id'],$link['connection_id']===null?null:(string)$link['connection_id'],
                $shop->value,$deliveryHash,$hash,$topic,$envelope,$triggered,$status,
            ]);
            (new AuditLog($this->db))->append(new Scope($merchant,$store),Actor::system(),AuditAction::IntegrationWebhookReceived,$id,new SafePayload(['event_id'=>$id]));
        });
    }

    private function timestamp(string $value): ?int
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/D',$value)){return null;}
        $normalized=preg_replace('/(\.\d{6})\d+/', '$1',$value);
        try{$date=new \DateTimeImmutable($normalized??'');}catch(\Exception){return null;}
        $timestamp=(int)$date->format('Uu');return $timestamp<0?null:$timestamp;
    }
}
