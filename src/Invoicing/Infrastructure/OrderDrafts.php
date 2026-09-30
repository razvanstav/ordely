<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;
use Ordely\Core\Value\CanonicalJson;

/** One revisable preparation for the initial invoice of a CMS order, not the future invoice ledger. */
final readonly class OrderDrafts
{
    public function __construct(private Sql $db,private OrderPreparations $source,private PreparationCipher $cipher) {}
    public function save(TenantContext $actor,string $store,string $order,int $expectedVersion,int $orderVersion,int $profileVersion): int
    {
        if($expectedVersion<0||$expectedVersion>=4294967295||$orderVersion<1||$profileVersion<0){throw new \InvalidArgumentException('Invalid preparation version.');}
        return $this->db->transaction(function()use($actor,$store,$order,$expectedVersion,$orderVersion,$profileVersion):int{
            (new AccessPolicy($this->db))->require($actor,'invoices.draft',$store);
            // Serialize creation and refresh together with store/connection/profile configuration.
            $this->db->one('SELECT id FROM stores WHERE merchant_id=? AND id=? FOR UPDATE',[Id::bytes($actor->merchantId),Id::bytes($store)]);
            $snapshot=$this->source->get($actor,$store,$order);
            if($snapshot['source']['version']!==$orderVersion||($snapshot['seller']['version']??0)!==$profileVersion){throw new Conflict('preparation_source_changed');}
            $row=$this->row($actor,$store,$order,' FOR UPDATE');$current=$row===null?0:(int)$row['version'];
            $same=$row!==null&&(int)$row['order_version']===$orderVersion&&(int)$row['profile_version']===$profileVersion;
            // Exact retry, or re-saving an unchanged source, preserves the existing revision.
            if($same&&($current===$expectedVersion||$current===$expectedVersion+1)){
                $old=$this->decode($actor,$store,$order,$row);
                // Connection invalidation can change readiness without changing the profile revision.
                if(CanonicalJson::encode($old['seller'])===CanonicalJson::encode($snapshot['seller'])){return $current;}
            }
            if($current!==$expectedVersion){throw new Conflict('preparation_version_changed');}
            $version=$current+1;
            $envelope=$this->cipher->seal($actor->merchantId,$store,$order,$orderVersion,$profileVersion,$version,$snapshot);
            $this->db->run('INSERT INTO invoice_order_drafts(merchant_id,store_id,order_id,version,order_version,profile_version,snapshot_envelope) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE version=VALUES(version),order_version=VALUES(order_version),profile_version=VALUES(profile_version),snapshot_envelope=VALUES(snapshot_envelope)',[...$this->scope($actor,$store,$order),$version,$orderVersion,$profileVersion,$envelope]);
            $this->audit($actor,$store,$order,$version,AuditAction::InvoicePreparationSaved);
            return $version;
        });
    }
    /** @return array<string,mixed>|null */
    public function get(TenantContext $actor,string $store,string $order): ?array
    {
        return $this->db->transaction(function()use($actor,$store,$order):?array{
            $live=$this->source->get($actor,$store,$order);$row=$this->row($actor,$store,$order,' FOR SHARE');
            if($row===null){return null;}
            $snapshot=$this->decode($actor,$store,$order,$row);
            $changed=(int)$row['order_version']!==$live['source']['version']||(int)$row['profile_version']!==($live['seller']['version']??0)||CanonicalJson::encode($snapshot['seller'])!==CanonicalJson::encode($live['seller']);
            $this->audit($actor,$store,$order,(int)$row['version'],AuditAction::InvoicePreparationViewed);
            return ['orderId'=>$order,'storeId'=>$store,'version'=>(int)$row['version'],'snapshot'=>$snapshot,'sourceChanged'=>$changed,'updatedAt'=>$row['updated_at']];
        });
    }
    /** @return array{drafts:list<array<string,mixed>>,nextCursor:?string} */
    public function list(TenantContext $actor,string $store,?string $after=null): array
    {
        return $this->db->transaction(function()use($actor,$store,$after):array{
            $policy=new AccessPolicy($this->db);$policy->require($actor,'invoices.read',$store);$policy->require($actor,'orders.read',$store);
            $params=[Id::bytes($actor->merchantId),Id::bytes($store)];if($after!==null){$params[]=Id::bytes($after);}
            $rows=$this->db->run("SELECT d.* FROM invoice_order_drafts d JOIN commerce_records r ON r.merchant_id=d.merchant_id AND r.store_id=d.store_id AND r.id=d.order_id WHERE d.merchant_id=? AND d.store_id=? AND r.kind='order' AND r.active=TRUE".($after===null?'':' AND d.order_id>?').' ORDER BY d.order_id LIMIT 26',$params)->fetchAll();
            $more=count($rows)>25;$drafts=[];
            foreach(array_slice($rows,0,25) as $row){$order=bin2hex((string)$row['order_id']);$snapshot=$this->decode($actor,$store,$order,$row);$drafts[]=['orderId'=>$order,'version'=>(int)$row['version'],'reference'=>$snapshot['source']['reference'],'lineCount'=>count($snapshot['lines']),'total'=>$snapshot['totals']['current'],'updatedAt'=>$row['updated_at']];}
            return ['drafts'=>$drafts,'nextCursor'=>$more?$drafts[count($drafts)-1]['orderId']:null];
        });
    }
    /** @return array<string,mixed>|null */
    private function row(TenantContext $actor,string $store,string $order,string $lock): ?array { return $this->db->one('SELECT * FROM invoice_order_drafts WHERE merchant_id=? AND store_id=? AND order_id=?'.$lock,$this->scope($actor,$store,$order)); }
    /** @return list<string> */
    private function scope(TenantContext $actor,string $store,string $order): array { return [Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($order)]; }
    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function decode(TenantContext $actor,string $store,string $order,array $row): array { return $this->cipher->open($actor->merchantId,$store,$order,(int)$row['order_version'],(int)$row['profile_version'],(int)$row['version'],(string)$row['snapshot_envelope']); }
    private function audit(TenantContext $actor,string $store,string $order,int $version,AuditAction $action): void { (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),$action,$order,new SafePayload(['order_id'=>$order,'version'=>$version])); }
}
