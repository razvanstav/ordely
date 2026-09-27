<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
use Ordely\Core\Value\OperationKey;
final readonly class ExternalLease
{
    public function __construct(public string $id,public string $owner,public int $fence,public int $attempt,public OperationKey $providerKey) {}
}
