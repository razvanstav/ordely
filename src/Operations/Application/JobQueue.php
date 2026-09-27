<?php
declare(strict_types=1);
namespace Ordely\Operations\Application;
use Ordely\Core\Value\OperationKey;
use Ordely\Operations\Domain\{FailureCode,JobLease,SafePayload,Scope};
interface JobQueue
{
    public function enqueue(Scope $scope,string $type,SafePayload $payload,OperationKey $dedupe,int $maximum=5): string;
    public function claim(?string $merchantId=null,int $leaseSeconds=60): ?JobLease;
    public function guard(JobLease $lease): void;
    public function heartbeat(JobLease $lease,int $seconds=60): void;
    public function acknowledge(JobLease $lease): void;
    public function fail(JobLease $lease,FailureCode $error,?int $retryAfter=null): void;
}
