<?php
declare(strict_types=1);
namespace Ordely\Integrations\Application;
use Ordely\Core\Contracts\{CommerceConnector,CarrierProvider,InvoiceProvider};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};

final readonly class ProviderDefinition
{
    /** @param (\Closure(Secrets):(CommerceConnector|CarrierProvider|InvoiceProvider))|null $factory
     * @param list<string> $credentialFields */
    public function __construct(public string $key,public string $label,public ProviderKind $kind,public array $credentialFields=[],private ?\Closure $factory=null) {}
    public function available(): bool { return $this->factory!==null; }
    public function validate(Secrets $credentials): void
    {
        $actual=array_keys($credentials->reveal());$expected=$this->credentialFields;sort($actual);sort($expected);
        if(!$this->available()||$actual!==$expected){throw new \InvalidArgumentException('Provider configuration unavailable.');}
    }
    public function build(Secrets $credentials): CommerceConnector|CarrierProvider|InvoiceProvider
    {
        $this->validate($credentials);$factory=$this->factory??throw new \LogicException('Adapter unavailable.');$adapter=$factory($credentials);
        $valid=match($this->kind){ProviderKind::Commerce=>$adapter instanceof CommerceConnector,ProviderKind::Carrier=>$adapter instanceof CarrierProvider,ProviderKind::Invoice=>$adapter instanceof InvoiceProvider};
        if(!$valid){throw new \LogicException('Incorrect adapter contract.');}return $adapter;
    }
    /** @return array{key:string,label:string,kind:string,available:bool,credentialFields:list<string>} */
    public function metadata(): array { return ['key'=>$this->key,'label'=>$this->label,'kind'=>$this->kind->value,'available'=>$this->available(),'credentialFields'=>$this->credentialFields]; }
}
