<?php
declare(strict_types=1);
namespace Ordely\Operations\Application;
use Ordely\Core\Contracts\{ErrorCategory,ProviderFailure};
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Operations\Domain\{Conflict,FailureCode,LeaseLost};

final readonly class Worker
{
    /** @var array<string,JobHandler> */
    private array $handlers;
    /** @param list<JobHandler> $handlers */
    public function __construct(private JobQueue $queue,array $handlers)
    {
        $map=[];foreach($handlers as $handler){if(isset($map[$handler->type()])){throw new \InvalidArgumentException('Duplicate job handler.');}$map[$handler->type()]=$handler;}$this->handlers=$map;
    }
    public function once(?string $merchantId=null): string
    {
        $job=$this->queue->claim($merchantId);if($job===null){return 'idle';}
        try{
            $this->queue->guard($job);
            $handler=$this->handlers[$job->type] ?? null;
            if($handler===null){$this->queue->fail($job,FailureCode::HandlerMissing);return 'dead';}
            $handler->handle($job);$this->queue->acknowledge($job);return 'succeeded';
        }catch(LeaseLost){return 'lease_lost';}
        catch(\Throwable $error){
            $code=match(true){
                $error instanceof ProviderFailure => $error->category===ErrorCategory::Transient?FailureCode::Transient:($error->category===ErrorCategory::Unknown?FailureCode::Unknown:FailureCode::Invalid),
                $error instanceof AccessDenied => FailureCode::ScopeInactive,
                $error instanceof \InvalidArgumentException,$error instanceof Conflict => FailureCode::Invalid,
                default => FailureCode::Unknown,
            };
            try{$this->queue->fail($job,$code,$error instanceof ProviderFailure?$error->retryAfterSeconds:null);}
            catch(LeaseLost){return 'lease_lost';}
            return $code===FailureCode::Transient&&$job->attempt<$job->maximum?'retry':'dead';
        }
    }
}
