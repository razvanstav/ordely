<?php
declare(strict_types=1);
namespace Ordely\Identity\Infrastructure;

use Ordely\Identity\Application\Passwords;
use Ordely\Identity\Domain\Role;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Shared\Id;

/** Trusted operator CLI only. Never expose this service as a public signup endpoint. */
final readonly class Provisioner
{
    public function __construct(private Sql $db) {}

    /** @return array{merchantId:string,userId:string,membershipId:string} */
    public function create(string $name, string $email, #[\SensitiveParameter] string $password): array
    {
        if (trim($name) === '' || mb_strlen($name) > 160) { throw new \InvalidArgumentException('Invalid merchant name.'); }
        $email = Passwords::email($email);
        $hash = Passwords::hash($password);
        return $this->db->transaction(function () use ($name, $email, $hash): array {
            $merchant = Id::new(); $user = Id::new();
            $this->db->run('INSERT INTO merchants (id,name) VALUES (?,?)', [Id::bytes($merchant), $name]);
            $this->db->run('INSERT INTO users (id,email,password_hash) VALUES (?,?,?)', [Id::bytes($user), $email, $hash]);
            return ['merchantId' => $merchant, 'userId' => $user, 'membershipId' => $this->addMembership($merchant, $user, Role::Owner, true)];
        });
    }

    public function addMembership(string $merchant, string $user, Role $role, bool $allStores): string
    {
        $id = Id::new();
        $this->db->run('INSERT INTO memberships (id,merchant_id,user_id,role,all_stores) VALUES (?,?,?,?,?)', [Id::bytes($id), Id::bytes($merchant), Id::bytes($user), $role->value, (int) $allStores]);
        return $id;
    }

    public function grantStore(string $merchant, string $membership, string $store): void
    {
        $this->db->run('INSERT INTO membership_store_grants (merchant_id,membership_id,store_id) VALUES (?,?,?)', [Id::bytes($merchant), Id::bytes($membership), Id::bytes($store)]);
    }
}
