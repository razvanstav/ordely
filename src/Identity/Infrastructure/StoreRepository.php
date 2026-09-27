<?php
declare(strict_types=1);
namespace Ordely\Identity\Infrastructure;

use Ordely\Identity\Domain\{AccessDenied, TenantContext};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Shared\Id;
use Ordely\Operations\Infrastructure\RecordStoreChange;

final readonly class StoreRepository
{
    public function __construct(private Sql $db) {}

    /** @return list<array{id:string,name:string,platform:string}> */
    public function list(TenantContext $context): array
    {
        $context->require('stores.read');
        $rows = $this->db->run("SELECT LOWER(HEX(s.id)) id,s.name,s.platform_key platform FROM stores s
            JOIN merchants t ON t.id=s.merchant_id AND t.status='active'
            JOIN memberships m ON m.merchant_id=t.id AND m.id=? AND m.user_id=? AND m.status='active'
            JOIN users u ON u.id=m.user_id AND u.status='active'
            WHERE s.merchant_id=? AND s.status='active' AND (m.all_stores=1 OR EXISTS (
                SELECT 1 FROM membership_store_grants g WHERE g.merchant_id=s.merchant_id AND g.membership_id=m.id AND g.store_id=s.id)) ORDER BY s.name,s.id",
            [Id::bytes($context->membershipId), Id::bytes($context->userId), Id::bytes($context->merchantId)]);
        $result = [];
        while ($row = $rows->fetch()) {
            $result[] = ['id' => (string) $row['id'], 'name' => (string) $row['name'], 'platform' => (string) $row['platform']];
        }
        return $result;
    }

    /** @return array{id:string,name:string,platform:string}|null */
    public function get(TenantContext $context, string $id): ?array
    {
        Id::bytes($id);
        foreach ($this->list($context) as $store) { if ($store['id'] === $id) { return $store; } }
        return null;
    }

    public function create(TenantContext $context, string $name, string $platform): string
    {
        $this->validateName($name);
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $platform)) { throw new \InvalidArgumentException('Invalid platform.'); }
        return $this->db->transaction(function () use ($context, $name, $platform): string {
            $this->assertManager($context);
            $id = Id::new();
            $this->db->run('INSERT INTO stores (id,merchant_id,name,platform_key) VALUES (?,?,?,?)', [Id::bytes($id), Id::bytes($context->merchantId), $name, $platform]);
            if (!$context->allStores) { (new Provisioner($this->db))->grantStore($context->merchantId, $context->membershipId, $id); }
            (new RecordStoreChange($this->db))->record($context,$id,1,true);
            return $id;
        });
    }

    public function rename(TenantContext $context, string $id, string $name): bool
    {
        $this->validateName($name);
        return $this->db->transaction(function () use ($context, $id, $name): bool {
            $this->assertManager($context);
            if ($this->get($context, $id) === null) { return false; }
            $this->db->run('UPDATE stores SET name=?,version=version+1 WHERE merchant_id=? AND id=?', [$name, Id::bytes($context->merchantId), Id::bytes($id)]);
            $version=(int)$this->db->run('SELECT version FROM stores WHERE merchant_id=? AND id=?',[Id::bytes($context->merchantId),Id::bytes($id)])->fetchColumn();
            (new RecordStoreChange($this->db))->record($context,$id,$version,false);
            return true;
        });
    }

    private function assertManager(TenantContext $context): void
    {
        $context->require('stores.manage');
        $row = $this->db->one("SELECT m.id FROM memberships m JOIN merchants t ON t.id=m.merchant_id JOIN users u ON u.id=m.user_id
            WHERE m.merchant_id=? AND m.id=? AND m.user_id=? AND m.status='active' AND t.status='active' AND u.status='active'
            AND m.role IN ('owner','admin') FOR SHARE", [Id::bytes($context->merchantId), Id::bytes($context->membershipId), Id::bytes($context->userId)]);
        if ($row === null) { throw new AccessDenied('forbidden'); }
    }

    private function validateName(string $name): void
    {
        if (trim($name) === '' || mb_strlen($name) > 160) { throw new \InvalidArgumentException('Invalid store name.'); }
    }
}
