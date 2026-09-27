<?php
declare(strict_types=1);
namespace Ordely\Operations\Infrastructure;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Shared\Id;

final readonly class OperationsQuery
{
    public function __construct(private Sql $db) {}
    /** @return array{jobs:list<array<string,mixed>>,operations:list<array<string,mixed>>,audit:list<array<string,mixed>>} */
    public function read(TenantContext $actor): array
    {
        (new AccessPolicy($this->db))->require($actor,'operations.manage');
        return [
            'jobs'=>$this->rows($actor,'jobs','r.type,r.status,r.attempt_count,r.last_error,r.available_at','created_at'),
            'operations'=>$this->rows($actor,'external_operations','r.type,r.status,r.attempt_count,r.safe_error,r.fencing_version','created_at'),
            'audit'=>$this->rows($actor,'audit_logs','r.action,r.occurred_at,r.safe_data','occurred_at'),
        ];
    }
    /** SQL identifiers below are private, fixed callers; never accept them from HTTP.
     * @return list<array<string,mixed>> */
    private function rows(TenantContext $actor,string $table,string $fields,string $order): array
    {
        $statement=$this->db->run('SELECT LOWER(HEX(r.id)) id,LOWER(HEX(r.store_id)) store_id,'.$fields.' FROM '.$table.' r
            JOIN memberships m ON m.merchant_id=r.merchant_id AND m.id=? AND m.user_id=? AND m.status=\'active\' AND m.role IN (\'owner\',\'admin\')
            JOIN merchants t ON t.id=m.merchant_id AND t.status=\'active\' JOIN users u ON u.id=m.user_id AND u.status=\'active\'
            WHERE r.merchant_id=? AND (m.all_stores=1 OR (r.store_id IS NOT NULL AND EXISTS(SELECT 1 FROM membership_store_grants g WHERE g.merchant_id=r.merchant_id AND g.membership_id=m.id AND g.store_id=r.store_id))) ORDER BY r.'.$order.' DESC,r.id DESC LIMIT 100',
            [Id::bytes($actor->membershipId),Id::bytes($actor->userId),Id::bytes($actor->merchantId)]);
        $rows=[];while($row=$statement->fetch()){if(is_array($row)){$rows[]=$row;}}return $rows;
    }
}
