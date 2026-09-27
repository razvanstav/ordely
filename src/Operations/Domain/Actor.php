<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
use Ordely\Shared\Id;
final readonly class Actor
{
    private function __construct(public ?string $userId) { if($userId!==null){Id::bytes($userId);} }
    public static function user(string $id): self { return new self($id); }
    public static function system(): self { return new self(null); }
    public function type(): string { return $this->userId===null?'system':'user'; }
}
