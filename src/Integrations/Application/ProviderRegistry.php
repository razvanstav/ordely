<?php
declare(strict_types=1);
namespace Ordely\Integrations\Application;

final readonly class ProviderRegistry
{
    /** @var array<string,ProviderDefinition> */
    private array $definitions;
    public function __construct(ProviderDefinition ...$definitions)
    {
        $map=[];foreach($definitions as $definition){if(isset($map[$definition->key])){throw new \LogicException('Duplicate provider.');}$map[$definition->key]=$definition;}$this->definitions=$map;
    }
    public function get(string $key): ProviderDefinition { return $this->definitions[$key]??throw new \InvalidArgumentException('Unknown provider.'); }
    /** @return list<array{key:string,label:string,kind:string,available:bool,credentialFields:list<string>}> */
    public function metadata(): array { return array_map(static fn(ProviderDefinition $definition):array=>$definition->metadata(),array_values($this->definitions)); }
}
