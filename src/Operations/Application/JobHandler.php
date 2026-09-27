<?php
declare(strict_types=1);
namespace Ordely\Operations\Application;
use Ordely\Operations\Domain\JobLease;
interface JobHandler
{
    public function type(): string;
    public function handle(JobLease $job): void;
}
