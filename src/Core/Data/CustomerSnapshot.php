<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\{Address, EmailAddress, ExternalId};
final readonly class CustomerSnapshot
{
    public function __construct(public ExternalId $id, public string $name, public ?EmailAddress $email, public Address $shipping, public Address $billing)
    {
        if (trim($name) === '' || mb_strlen($name) > 160) { throw new \InvalidArgumentException('Invalid customer snapshot.'); }
    }
}
