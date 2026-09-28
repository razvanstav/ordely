<?php
declare(strict_types=1);
namespace Ordely\Adapters\Shopify;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\SecretCipher;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

/** Local fulfillment only. Delivery to the requester remains an explicit merchant operation. */
final readonly class PrivacyRequests
{
    public function __construct(private Sql $db,private SecretCipher $cipher) {}
    /** @return array<string,mixed> */
    public function process(TenantContext $actor,string $event,string $action): array
    {
        return $this->db->transaction(function() use ($actor,$event,$action): array {
            $row=$this->db->one('SELECT * FROM shopify_webhook_events WHERE merchant_id=? AND id=?',[Id::bytes($actor->merchantId),Id::bytes($event)]);
            if ($row===null) { throw new AccessDenied('privacy_unavailable'); }
            $store=bin2hex((string)$row['store_id']);(new AccessPolicy($this->db))->require($actor,'operations.manage',$store);
            $link=$this->db->one('SELECT * FROM shopify_links WHERE merchant_id=? AND store_id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $row=$this->db->one('SELECT * FROM shopify_webhook_events WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($event)]);
            if ($row===null || $link===null || !in_array($row['topic'],['customers/data_request','customers/redact','shop/redact'],true)) { throw new Conflict('invalid_privacy_request'); }
            if ($row['status']==='processed') { return ['status'=>'processed']; }
            $parts=$this->cipher->decrypt($actor->merchantId,$event,'shopify-webhook',(string)$row['payload_envelope'])->reveal();
            $raw=base64_decode(implode('',$parts),true);if($raw===false){throw new Conflict('invalid_privacy_payload');}
            $payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($payload)) { throw new Conflict('invalid_privacy_payload'); }
            $shop=$row['topic']==='shop/redact';$export=$row['topic']==='customers/data_request';
            if (($export && !in_array($action,['export','confirm-delivered'],true)) || (!$export && $action!=='redact')) { throw new Conflict('invalid_privacy_action'); }
            // A delayed shop/redact must never erase an active reinstall.
            if ($shop && ($this->db->one("SELECT 1 FROM provider_connections WHERE merchant_id=? AND id=? AND status='active'",[(string)$link['merchant_id'],$link['connection_id']])!==null || $row['connection_id']!==$link['connection_id'])) { throw new Conflict('shop_reinstalled'); }
            $customer=$this->gid($payload['customer']['id']??null,'Customer');$orderIds=[];
            foreach($payload[$export?'orders_requested':'orders_to_redact']??[] as $external) { $orderIds[]=$this->gid($external,'Order'); }
            $orderIds=array_values(array_filter($orderIds));
            if (!$shop && $customer===null && $orderIds===[]) { throw new Conflict('privacy_subject_missing'); }
            $runs=$this->db->run("SELECT id FROM commerce_sync_runs WHERE merchant_id=? AND store_id=? AND status='running' FOR UPDATE",[Id::bytes($actor->merchantId),Id::bytes($store)])->fetchAll();
            $orderCipher=new OrderCipher($this->cipher);$orders=[];
            foreach(['commerce_records','commerce_sync_records'] as $table) {
                $rows=$this->db->run("SELECT * FROM ".$table." WHERE merchant_id=? AND store_id=? AND kind='order' FOR UPDATE",[Id::bytes($actor->merchantId),Id::bytes($store)])->fetchAll();
                foreach($rows as $record) {
                    $id=bin2hex((string)$record[$table==='commerce_records'?'id':'record_id']);
                    $document=$orderCipher->open($actor->merchantId,$store,$id,(string)$record['document']);
                    if (!$shop && !in_array($record['external_id'],$orderIds,true) && ($customer===null || ($document['customerId']??null)!==$customer)) { continue; }
                    if ($export) { $orders[(string)$record['external_id']]=$document; }
                    else {
                        $this->block($actor->merchantId,$store,'order',(string)$record['external_id']);
                        $this->db->run('DELETE FROM '.$table.' WHERE merchant_id=? AND store_id=? AND kind=? AND external_id=?',[Id::bytes($actor->merchantId),Id::bytes($store),'order',(string)$record['external_id']]);
                    }
                }
            }
            if ($action==='export') {
                (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::PrivacyProcessed,$event,new SafePayload(['count'=>count($orders)]));
                return ['status'=>'prepared_not_delivered','orders'=>array_values($orders)];
            }
            if (!$export) {
                if ($customer!==null) { $this->block($actor->merchantId,$store,'customer',$customer); }
                foreach($orderIds as $external) { $this->block($actor->merchantId,$store,'order',$external); }
                if ($shop) {
                    $this->block($actor->merchantId,$store,'shop','all');
                    foreach($runs as $run) { $this->db->run("UPDATE commerce_sync_runs SET status='cancelled' WHERE id=?",[(string)$run['id']]); }
                    $this->db->run('DELETE FROM commerce_sync_records WHERE merchant_id=? AND store_id=?',[Id::bytes($actor->merchantId),Id::bytes($store)]);
                    $this->db->run('DELETE FROM commerce_records WHERE merchant_id=? AND store_id=?',[Id::bytes($actor->merchantId),Id::bytes($store)]);
                }
            }
            $envelope=$this->cipher->encrypt($actor->merchantId,$event,'shopify-webhook',new Secrets(['body0'=>base64_encode('{}')]));
            $this->db->run("UPDATE shopify_webhook_events SET status='processed',payload_envelope=? WHERE merchant_id=? AND id=?",[$envelope,Id::bytes($actor->merchantId),Id::bytes($event)]);
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::PrivacyProcessed,$event,new SafePayload(['event_id'=>$event]));
            return ['status'=>'processed'];
        });
    }
    private function gid(mixed $value,string $type): ?string
    {
        if ($value===null) { return null; }
        if ((!is_int($value)&&!is_string($value)) || !preg_match('/^[1-9][0-9]{0,24}$/D',(string)$value)) { throw new Conflict('invalid_privacy_subject'); }
        return 'gid://shopify/'.$type.'/'.$value;
    }
    private function block(string $merchant,string $store,string $type,string $external): void
    {
        $this->db->run('INSERT INTO commerce_privacy_blocks(merchant_id,store_id,subject_type,external_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE external_id=external_id',[Id::bytes($merchant),Id::bytes($store),$type,$external]);
    }
}
