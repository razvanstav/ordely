<?php
declare(strict_types=1);
namespace Ordely\Tests\Integration;

use Ordely\Identity\Domain\{AccessDenied, Role, TenantContext};
use Ordely\Identity\Infrastructure\{Provisioner, Sessions, StoreRepository};
use Ordely\Infrastructure\Http\Application;
use Ordely\Shared\Id;
use Ordely\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\{Request, Response};

final class IdentityTest extends DatabaseTestCase
{
    private function login(TenantContext $context): string
    {
        return (new Sessions($this->db))->login($context->userId . '@example.test', self::PASSWORD, '127.0.0.1');
    }

    /** @param array<string,mixed> $data
     * @param array<string,string> $headers */
    private function request(string $path, string $method = 'GET', string $token = '', array $data = [], bool $csrf = true, array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1','HTTP_IDEMPOTENCY_KEY'=>Id::new()];
        if ($csrf) { $server['HTTP_X_CSRF_TOKEN'] = Sessions::csrf($token); }
        $request = Request::create('https://localhost' . $path, $method, [], ['ordely_session' => $token], [], array_merge($server, $headers), json_encode((object) $data, JSON_THROW_ON_ERROR));
        return (new Application(fn (): \PDO => $this->db->pdo))->handle($request);
    }

    public function testTenantCannotReadOrRenameForeignStoreEvenWithForgedHeadersAndBody(): void
    {
        $a = $this->tenant(); $b = $this->tenant();
        $own = $this->store($a, 'Same external label'); $foreign = $this->store($b, 'Same external label');
        $repository = new StoreRepository($this->db);
        self::assertNull($repository->get($a, $foreign));
        self::assertSame([$own], array_column($repository->list($a), 'id'));
        $token = $this->login($a);
        self::assertSame(404, $this->request('/api/stores/' . $foreign, token: $token, headers: ['HTTP_X_MERCHANT_ID' => $b->merchantId])->getStatusCode());
        self::assertSame(404, $this->request('/api/stores/' . $foreign, 'PATCH', $token, ['name' => 'Hijacked', 'merchantId' => $b->merchantId])->getStatusCode());
        $unchanged = $repository->get($b, $foreign);
        self::assertNotNull($unchanged);
        self::assertSame('Same external label', $unchanged['name']);
        self::assertSame(201, $this->request('/api/stores', 'POST', $token, ['name' => 'New', 'platform' => 'manual', 'merchantId' => $b->merchantId])->getStatusCode());
        self::assertCount(2, $repository->list($a)); self::assertCount(1, $repository->list($b));
    }

    public function testDatabaseRejectsCrossTenantGrant(): void
    {
        $a = $this->tenant(); $b = $this->tenant(); $foreign = $this->store($b);
        $this->expectException(\PDOException::class);
        (new Provisioner($this->db))->grantStore($a->merchantId, $a->membershipId, $foreign);
    }

    public function testRestrictedMembershipSeesOnlyGrantedStore(): void
    {
        $a = $this->tenant(Role::Viewer, false); $visible = $this->store($a); $hidden = $this->store($a);
        (new Provisioner($this->db))->grantStore($a->merchantId, $a->membershipId, $visible);
        $token = $this->login($a);
        self::assertSame(200, $this->request('/api/stores/' . $visible, token: $token)->getStatusCode());
        self::assertSame(404, $this->request('/api/stores/' . $hidden, token: $token)->getStatusCode());
        self::assertSame(403, $this->request('/api/stores', 'POST', $token, ['name' => 'No', 'platform' => 'manual'])->getStatusCode());
        $this->db->run('DELETE FROM membership_store_grants WHERE merchant_id=?', [Id::bytes($a->merchantId)]);
        self::assertSame(404, $this->request('/api/stores/' . $visible, token: $token)->getStatusCode());
    }

    public function testAllNonManagerRolesCannotMutateStoresAndUnknownPermissionDenied(): void
    {
        foreach ([Role::Viewer, Role::Operator, Role::Finance] as $role) {
            $a = $this->tenant($role); $token = $this->login($a);
            self::assertSame(403, $this->request('/api/stores', 'POST', $token, ['name' => 'No', 'platform' => 'manual'])->getStatusCode());
        }
        self::assertFalse(Role::Owner->allows('unknown.permission'));
        self::assertTrue(Role::Admin->allows('stores.manage'));
    }

    public function testCsrfOriginAndJsonContentTypeAreRequired(): void
    {
        $token = $this->login($this->tenant());
        self::assertSame(403, $this->request('/api/auth/logout', 'POST', $token, csrf: false)->getStatusCode());
        self::assertSame(403, $this->request('/api/auth/logout', 'POST', $token, headers: ['HTTP_ORIGIN' => 'https://attacker.test'])->getStatusCode());
        self::assertSame(415, $this->request('/api/auth/logout', 'POST', $token, headers: ['CONTENT_TYPE' => 'text/plain'])->getStatusCode());
        self::assertSame(200, $this->request('/api/me', token: $token)->getStatusCode());
    }

    public function testLoginCookieAndLogoutRevocation(): void
    {
        $a = $this->tenant();
        $response = $this->request('/api/auth/login', 'POST', data: ['email' => $a->userId . '@example.test', 'password' => self::PASSWORD]);
        self::assertSame(200, $response->getStatusCode());
        $cookie = $response->headers->getCookies()[0]; $token = (string) $cookie->getValue();
        self::assertTrue($cookie->isHttpOnly()); self::assertTrue($cookie->isSecure()); self::assertSame('lax', $cookie->getSameSite());
        self::assertSame(64, strlen($token));
        self::assertSame(0, (int) $this->db->run('SELECT COUNT(*) FROM auth_sessions WHERE token_hash=?', [$token])->fetchColumn());
        self::assertStringNotContainsString($token, (string) $response->getContent());
        self::assertSame(200, $this->request('/api/auth/logout', 'POST', $token)->getStatusCode());
        self::assertSame(401, $this->request('/api/me', token: $token)->getStatusCode());
    }

    public function testSwitchRequiresMembershipAndRotatesSession(): void
    {
        $a = $this->tenant(); $b = $this->tenant(); $token = $this->login($a);
        self::assertSame(403, $this->request('/api/auth/merchant', 'POST', $token, ['merchantId' => $b->merchantId])->getStatusCode());
        (new Provisioner($this->db))->addMembership($b->merchantId, $a->userId, Role::Viewer, true);
        $response = $this->request('/api/auth/merchant', 'POST', $token, ['merchantId' => $b->merchantId]);
        self::assertSame(200, $response->getStatusCode());
        $new = (string) $response->headers->getCookies()[0]->getValue();
        self::assertNotSame($token, $new);
        self::assertSame(401, $this->request('/api/me', token: $token)->getStatusCode());
        self::assertSame($b->merchantId, (new Sessions($this->db))->resolve($new)->merchantId);
    }

    public function testDisabledUserMerchantMembershipAndExpiredSessionDenyAccess(): void
    {
        foreach (['users', 'merchants', 'memberships'] as $table) {
            $a = $this->tenant(); $token = $this->login($a);
            $id = match ($table) { 'users' => $a->userId, 'merchants' => $a->merchantId, default => $a->membershipId };
            $this->db->run('UPDATE ' . $table . " SET status='disabled' WHERE id=?", [Id::bytes($id)]);
            self::assertSame(401, $this->request('/api/me', token: $token)->getStatusCode());
            self::assertSame([], (new StoreRepository($this->db))->list($a));
        }
        $token = $this->login($this->tenant());
        $this->db->run('UPDATE auth_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE token_hash=?', [hash('sha256', $token, true)]);
        self::assertSame(401, $this->request('/api/me', token: $token)->getStatusCode());
    }

    public function testStaleOwnerContextCannotBypassRoleRevocation(): void
    {
        $a = $this->tenant();
        $this->db->run("UPDATE memberships SET role='viewer' WHERE id=?", [Id::bytes($a->membershipId)]);
        $this->expectException(AccessDenied::class);
        (new StoreRepository($this->db))->create($a, 'Denied', 'manual');
    }

    public function testInvalidLoginIsGenericAndRateLimitIsEnforced(): void
    {
        $a = $this->tenant(); $email = $a->userId . '@example.test';
        $known = $this->request('/api/auth/login', 'POST', data: ['email' => $email, 'password' => 'wrong']);
        $unknown = $this->request('/api/auth/login', 'POST', data: ['email' => 'missing@example.test', 'password' => 'wrong']);
        self::assertSame(401, $known->getStatusCode()); self::assertSame($known->getContent(), $unknown->getContent());
        $this->db->run('UPDATE auth_rate_limits SET attempts=20 WHERE bucket=?', [hash('sha256', 'account:' . $email, true)]);
        self::assertSame(429, $this->request('/api/auth/login', 'POST', data: ['email' => $email, 'password' => self::PASSWORD])->getStatusCode());
    }

    public function testProvisioningIsAtomicOnDuplicateEmail(): void
    {
        $a = $this->tenant(); $count = $this->db->run('SELECT COUNT(*) FROM merchants')->fetchColumn();
        try {
            (new Provisioner($this->db))->create('Duplicate', $a->userId . '@example.test', self::PASSWORD);
            self::fail('Expected duplicate email rejection.');
        } catch (\PDOException) { self::assertSame($count, $this->db->run('SELECT COUNT(*) FROM merchants')->fetchColumn()); }
    }
}
