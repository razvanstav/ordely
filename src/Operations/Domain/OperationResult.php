<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Core\Value\ExternalId;
final readonly class OperationResult
{
    public function __construct(public string $id,public ExternalState $state,public ?ExternalId $reference,public int $version,public ?int $retryAfterSeconds=null) {}
    public function requireConfirmed(): ExternalId
    {
        if($this->state===ExternalState::Confirmed&&$this->reference!==null){return $this->reference;}
        throw new ProviderFailure(match($this->state){ExternalState::Retryable,ExternalState::Pending,ExternalState::InFlight=>ErrorCategory::Transient,ExternalState::Unknown=>ErrorCategory::Unknown,default=>ErrorCategory::Validation},$this->retryAfterSeconds);
    }
}
