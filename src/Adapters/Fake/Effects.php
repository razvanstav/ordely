<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Contracts\{ConnectionContext, ErrorCategory, ProviderFailure};
use Ordely\Core\Value\{CanonicalJson, OperationKey};

/** In-memory simulator only. Durable coordination is a separate module. */
final class Effects
{
    /** @var array<string,array{hash:string,result:object}> */
    private array $results = [];
    private int $performed = 0;
    public function performed(): int { return $this->performed; }
    private ?ErrorCategory $failure = null;
    private bool $afterEffect = false;
    public function failNext(ErrorCategory $category, bool $afterEffect = false): void { $this->failure=$category; $this->afterEffect=$afterEffect; }

    /** @template T of object
     * @param class-string<T> $type
     * @param \Closure():T $effect
     * @return T */
    public function once(ConnectionContext $context, string $operation, OperationKey $key, mixed $request, string $type, \Closure $effect): object
    {
        $scope=$context->scope().':'.$operation.':'.$key->value;
        $hash=hash('sha256',CanonicalJson::encode($request));
        if (isset($this->results[$scope])) {
            $saved=$this->results[$scope];
            if ($saved['hash']!==$hash) { throw new ProviderFailure(ErrorCategory::Conflict); }
            if (!$saved['result'] instanceof $type) { throw new \LogicException('Fake result type mismatch.'); }
            return $saved['result'];
        }
        $failure=$this->failure; $this->failure=null;
        if ($failure!==null && !$this->afterEffect) { throw new ProviderFailure($failure); }
        $result=$effect(); ++$this->performed;
        $this->results[$scope]=['hash'=>$hash,'result'=>$result];
        if ($failure!==null) { throw new ProviderFailure($failure); }
        return $result;
    }
}
