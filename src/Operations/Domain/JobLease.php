<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
final readonly class JobLease
{
    public function __construct(public Scope $scope,public string $id,public string $type,public SafePayload $payload,public string $owner,public int $fence,public int $attempt,public int $maximum) {}
}
