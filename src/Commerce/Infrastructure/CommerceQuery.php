<?php
declare(strict_types=1);
namespace Ordely\Commerce\Infrastructure;

use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\{Actor,AuditAction,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

final readonly class CommerceQuery
{
    public function __construct(private Sql $db,private OrderCipher $cipher) {}
    /** @return array<string,mixed> */
    public function list(TenantContext $actor,string $store,string $kind,?string $after=null): array
    {
        (new AccessPolicy($this->db))->require($actor,'stores.read',$store);
        if (!in_array($kind,['order','product','variant','inventory'],true)) { throw new \InvalidArgumentException('Invalid collection.'); }
        $parameters=[Id::bytes($actor->merchantId),Id::bytes($store),$kind];
        if ($after!==null) { $parameters[]=Id::bytes($after); }
        $rows=$this->db->run("SELECT r.*,JSON_UNQUOTE(JSON_EXTRACT(p.document,'$.title')) parent_title,JSON_UNQUOTE(JSON_EXTRACT(g.document,'$.title')) product_title
            FROM commerce_records r LEFT JOIN commerce_records p ON p.merchant_id=r.merchant_id AND p.store_id=r.store_id AND p.provider_key=r.provider_key AND p.external_id=r.parent_id AND p.kind=IF(r.kind='inventory','variant','product')
            LEFT JOIN commerce_records g ON g.merchant_id=r.merchant_id AND g.store_id=r.store_id AND g.provider_key=r.provider_key AND g.external_id=p.parent_id AND g.kind='product'
            WHERE r.merchant_id=? AND r.store_id=? AND r.kind=? AND r.active=TRUE".($after===null?'':' AND r.id>?').' ORDER BY r.id LIMIT 26',$parameters)->fetchAll();
        $more=count($rows)>25;$rows=array_slice($rows,0,25);$records=[];
        foreach($rows as $row) {
            $id=bin2hex((string)$row['id']);
            $document=$kind==='order'?$this->cipher->open($actor->merchantId,$store,$id,(string)$row['document']):json_decode((string)$row['document'],true,64,JSON_THROW_ON_ERROR);
            if ($kind==='order') { $document=array_intersect_key($document,array_flip(['number','paymentStatus','fulfillmentStatus','cancelledAt','totals','updatedAt','test'])); }
            $records[]=['id'=>$id,'version'=>(int)$row['version'],'observedAt'=>(string)$row['observed_at'],'parentTitle'=>implode(' · ',array_filter([$row['product_title'],$row['parent_title']],static fn(mixed $value):bool=>is_string($value)&&$value!=='')),'data'=>$document];
        }
        $run=$this->db->one("SELECT LOWER(HEX(r.id)) id,r.status,r.started_at,r.completed_at,COUNT(t.id) tasks,COALESCE(SUM(t.done),0) done,COALESCE(SUM(j.status='DEAD'),0) failed FROM commerce_sync_heads h JOIN commerce_sync_runs r ON r.id=h.run_id LEFT JOIN commerce_sync_tasks t ON t.run_id=r.id LEFT JOIN jobs j ON j.id=t.job_id WHERE h.merchant_id=? AND h.store_id=? AND h.provider_key='shopify' GROUP BY r.id",[Id::bytes($actor->merchantId),Id::bytes($store)]);
        $counts=$this->db->run('SELECT kind,COUNT(*) count FROM commerce_records WHERE merchant_id=? AND store_id=? AND active=TRUE GROUP BY kind',[Id::bytes($actor->merchantId),Id::bytes($store)])->fetchAll();
        $connection=$this->db->one("SELECT LOWER(HEX(c.id)) id FROM provider_connections c JOIN store_provider_bindings b ON b.merchant_id=c.merchant_id AND b.connection_id=c.id WHERE c.merchant_id=? AND b.store_id=? AND c.provider_key='shopify' AND c.status='active' LIMIT 1",[Id::bytes($actor->merchantId),Id::bytes($store)]);
        return ['records'=>$records,'nextCursor'=>$more?$records[count($records)-1]['id']:null,'run'=>$run,'counts'=>$counts,'connectionId'=>$connection['id']??null];
    }
    /** @return array<string,mixed> */
    public function order(TenantContext $actor,string $store,string $id): array
    {
        (new AccessPolicy($this->db))->require($actor,'orders.read',$store);
        $row=$this->db->one("SELECT document FROM commerce_records WHERE merchant_id=? AND store_id=? AND id=? AND kind='order' AND active=TRUE",[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($id)]);
        if ($row===null) { throw new AccessDenied('order_unavailable'); }
        $document=$this->cipher->open($actor->merchantId,$store,$id,(string)$row['document']);
        (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::OrderViewed,$id,new SafePayload(['order_id'=>$id]));
        return $document;
    }
}
