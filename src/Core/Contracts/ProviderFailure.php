<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
final class ProviderFailure extends \RuntimeException
{
    public function __construct(public readonly ErrorCategory $category, public readonly ?int $retryAfterSeconds = null)
    {
        if ($retryAfterSeconds !== null && ($retryAfterSeconds < 0 || $retryAfterSeconds > 86400)) { throw new \InvalidArgumentException('Invalid retry delay.'); }
        parent::__construct('provider.' . $category->value);
    }
    public function retryable(): bool { return $this->category === ErrorCategory::Transient; }
}
