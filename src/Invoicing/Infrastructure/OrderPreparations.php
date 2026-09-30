<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Infrastructure;

use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Invoicing\Domain\OrderPreparation;
use Ordely\Operations\Domain\{Actor,AuditAction,SafePayload,Scope};
use Ordely\Operations\Infrastructure\AuditLog;
use Ordely\Shared\Id;

final readonly class OrderPreparations
{
    public function __construct(private Sql $db,private OrderCipher $cipher,private InvoiceProfiles $profiles) {}
    /** @return array<string,mixed> */
    public function get(TenantContext $actor,string $store,string $orderId): array
    {
        return $this->db->transaction(function()use($actor,$store,$orderId):array{
            $policy=new AccessPolicy($this->db);$policy->require($actor,'invoices.read',$store);$policy->require($actor,'orders.read',$store);
            $row=$this->db->one("SELECT document,version,observed_at,source_version FROM commerce_records WHERE merchant_id=? AND store_id=? AND id=? AND kind='order' AND active=TRUE FOR SHARE",[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($orderId)]);
            if($row===null){throw new AccessDenied('order_unavailable');}
            $order=$this->cipher->open($actor->merchantId,$store,$orderId,(string)$row['document']);
            $result=OrderPreparation::build($order,$this->profiles->get($actor,$store));
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$store),Actor::user($actor->userId),AuditAction::OrderViewed,$orderId,new SafePayload(['order_id'=>$orderId,'version'=>(int)$row['version']]));
            return [...$result,'source'=>['orderId'=>$orderId,'storeId'=>$store,'version'=>(int)$row['version'],'updatedAt'=>$row['source_version'],'observedAt'=>$row['observed_at'],'reference'=>$order['number']??null]];
        });
    }
}
