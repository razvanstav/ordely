<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Shared\Id;
final readonly class Scope
{
    public function __construct(public string $merchantId,public ?string $storeId=null)
    {
        Id::bytes($merchantId);if($storeId!==null){Id::bytes($storeId);}
    }
    public static function connection(ConnectionContext $context): self { return new self($context->merchant->value,$context->store->value); }
    public function key(): string { return $this->merchantId.':'.($this->storeId ?? 'merchant'); }
}
