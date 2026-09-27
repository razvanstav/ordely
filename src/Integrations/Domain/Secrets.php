<?php
declare(strict_types=1);
namespace Ordely\Integrations\Domain;

final readonly class Secrets implements \JsonSerializable
{
    /** @param array<string,string> $values */
    public function __construct(#[\SensitiveParameter] private array $values)
    {
        if($values===[]||count($values)>16){throw new \InvalidArgumentException('Invalid credentials.');}
        foreach($values as $name=>$value){
            if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D',$name)||$value===''||strlen($value)>4096||str_contains($value,"\0")){throw new \InvalidArgumentException('Invalid credentials.');}
        }
    }
    /** Only server-side adapters and the encryption boundary may call this.
     * @return array<string,string> */
    public function reveal(): array { return $this->values; }
    public function jsonSerialize(): string { return '[REDACTED]'; }
    /** @return array<string,string> */
    public function __debugInfo(): array { return ['credentials'=>'[REDACTED]']; }
    /** @return never */
    public function __serialize(): array { throw new \LogicException('Secrets cannot be serialized.'); }
}
