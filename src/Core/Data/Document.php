<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
final readonly class Document implements \JsonSerializable
{
    public function __construct(#[\SensitiveParameter] private string $bytes, public string $mime)
    {
        if ($bytes === '' || strlen($bytes) > 10_000_000 || !in_array($mime,['application/pdf','application/zpl'],true)) { throw new \InvalidArgumentException('Invalid document.'); }
    }
    public function contents(): string { return $this->bytes; }
    /** @return array{mime:string,size:int,sha256:string} */
    public function jsonSerialize(): array { return ['mime'=>$this->mime,'size'=>strlen($this->bytes),'sha256'=>hash('sha256',$this->bytes)]; }
}
