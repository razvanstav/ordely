<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Identity\Domain\{Role,TenantContext};

abstract class CommittedDatabaseTestCase extends DatabaseTestCase
{
    /** @var list<TenantContext> */
    private array $fixtures=[];
    protected function setUp(): void { parent::setUp();$this->db->pdo->commit(); }
    protected function tenant(Role $role=Role::Owner,bool $allStores=true): TenantContext
    {
        $context=$this->db->transaction(fn():TenantContext=>parent::tenant($role,$allStores));$this->fixtures[]=$context;return $context;
    }
    protected function tearDown(): void
    {
        parent::tearDown();
        foreach(array_reverse($this->fixtures) as $fixture){FixtureCleanup::merchant($this->db,$fixture->merchantId,$fixture->userId);}
    }
}
