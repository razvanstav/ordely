<?php
declare(strict_types=1);
namespace Ordely\Identity\Infrastructure;

use Ordely\Identity\Domain\{Role, TenantContext};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Shared\Id;

final readonly class Sessions
{
    // Valid bcrypt cost-12 hash ensures unknown accounts perform the same password work.
    private const DUMMY = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(private Sql $db) {}

    public function login(string $email, #[\SensitiveParameter] string $password, string $ip): string
    {
        $email = strtolower(trim($email));
        $this->limit('ip:' . $ip, 100);
        $this->limit('account:' . $email, 20);
        $row = $this->db->one('SELECT id,password_hash,status FROM users WHERE email=?', [$email]);
        $hash = $row === null ? self::DUMMY : (string) $row['password_hash'];
        $valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
        if (!$valid || $row === null || $row['status'] !== 'active') { throw new Problem(401, 'invalid_credentials'); }
        $user = bin2hex((string) $row['id']);
        $memberships = $this->merchants($user);
        if ($memberships === []) { throw new Problem(401, 'invalid_credentials'); }
        return $this->issue($user, $memberships[0]['id']);
    }

    public function resolve(#[\SensitiveParameter] string $token): TenantContext
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new Problem(401, 'unauthenticated'); }
        $row = $this->db->one("SELECT s.merchant_id,s.membership_id,s.user_id,m.role,m.all_stores FROM auth_sessions s
            JOIN memberships m ON m.merchant_id=s.merchant_id AND m.id=s.membership_id AND m.user_id=s.user_id
            JOIN users u ON u.id=s.user_id JOIN merchants t ON t.id=s.merchant_id
            WHERE s.token_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP(6)
            AND m.status='active' AND u.status='active' AND t.status='active'", [hash('sha256', $token, true)]);
        if ($row === null) { throw new Problem(401, 'unauthenticated'); }
        return new TenantContext(bin2hex((string) $row['merchant_id']), bin2hex((string) $row['membership_id']), bin2hex((string) $row['user_id']), Role::from((string) $row['role']), (bool) $row['all_stores']);
    }

    /** @return list<array{id:string,name:string}> */
    public function merchants(string $user): array
    {
        $rows = $this->db->run("SELECT LOWER(HEX(t.id)) id,t.name FROM memberships m JOIN merchants t ON t.id=m.merchant_id
            WHERE m.user_id=? AND m.status='active' AND t.status='active' ORDER BY t.id", [Id::bytes($user)]);
        $result = [];
        while ($row = $rows->fetch()) { $result[] = ['id' => (string) $row['id'], 'name' => (string) $row['name']]; }
        return $result;
    }

    public function switchMerchant(#[\SensitiveParameter] string $token, string $merchant): string
    {
        return $this->db->transaction(function () use ($token, $merchant): string {
            $context = $this->resolve($token);
            $new = $this->issue($context->userId, $merchant);
            $this->revoke($token);
            return $new;
        });
    }

    public function revoke(#[\SensitiveParameter] string $token): void
    {
        $this->db->run('UPDATE auth_sessions SET revoked_at=UTC_TIMESTAMP(6) WHERE token_hash=?', [hash('sha256', $token, true)]);
    }

    public static function csrf(#[\SensitiveParameter] string $token): string { return hash_hmac('sha256', 'ordely.csrf.v1', $token); }

    private function issue(string $user, string $merchant): string
    {
        $row = $this->db->one("SELECT m.id FROM memberships m JOIN users u ON u.id=m.user_id JOIN merchants t ON t.id=m.merchant_id
            WHERE m.user_id=? AND m.merchant_id=? AND m.status='active' AND u.status='active' AND t.status='active'", [Id::bytes($user), Id::bytes($merchant)]);
        if ($row === null) { throw new Problem(403, 'forbidden'); }
        $token = bin2hex(random_bytes(32));
        $this->db->run('INSERT INTO auth_sessions (token_hash,merchant_id,membership_id,user_id,expires_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 8 HOUR))',
            [hash('sha256', $token, true), Id::bytes($merchant), (string) $row['id'], Id::bytes($user)]);
        return $token;
    }

    private function limit(string $key, int $maximum): void
    {
        $count = $this->db->transaction(function () use ($key): int {
            $bucket = hash('sha256', $key, true);
            $this->db->run('INSERT INTO auth_rate_limits (bucket,attempts,expires_at) VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE))
                ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=UTC_TIMESTAMP(6),1,attempts+1),
                expires_at=IF(expires_at<=UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE),expires_at)', [$bucket]);
            return (int) $this->db->run('SELECT attempts FROM auth_rate_limits WHERE bucket=? FOR UPDATE', [$bucket])->fetchColumn();
        });
        if ($count > $maximum) { throw new Problem(429, 'too_many_attempts'); }
    }
}
