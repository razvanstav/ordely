<?php
declare(strict_types=1);
namespace Ordely\Integrations\Infrastructure;
use Ordely\Infrastructure\Configuration\Environment;

final readonly class KeyRing
{
    /** @param array<string,string> $keys Raw 32-byte keys. */
    public function __construct(public string $active,#[\SensitiveParameter] private array $keys)
    {
        if(count($keys)<1||count($keys)>32||!isset($keys[$active])){throw new \RuntimeException('Keyring unavailable.');}
        foreach($keys as $id=>$key){if(!preg_match('/^[a-z][a-z0-9-]{0,63}$/D',$id)||strlen($key)!==32){throw new \RuntimeException('Keyring unavailable.');}}
    }
    public static function fromEnvironment(): self
    {
        $file=Environment::string('ORDELY_KEYRING_FILE',dirname(__DIR__,3).'/var/keys/keyring.json');
        if(!is_file($file)||!is_readable($file)||filesize($file)>8192){throw new \RuntimeException('Keyring unavailable.');}
        $contents=file_get_contents($file);
        if($contents===false){throw new \RuntimeException('Keyring unavailable.');}
        return self::fromJson($contents);
    }
    public static function fromJson(#[\SensitiveParameter] string $json): self
    {
        try{
            $data=json_decode($json,true,8,JSON_THROW_ON_ERROR);
            if(!is_array($data)||!is_string($data['active']??null)||!is_array($data['keys']??null)){throw new \RuntimeException();}
            $keys=[];
            foreach($data['keys'] as $id=>$encoded){
                if(!is_string($id)||!is_string($encoded)){throw new \RuntimeException();}
                $key=base64_decode($encoded,true);
                if($key===false||base64_encode($key)!==$encoded){throw new \RuntimeException();}$keys[$id]=$key;
            }
            return new self($data['active'],$keys);
        }catch(\Throwable){throw new \RuntimeException('Keyring unavailable.');}
    }
    public function key(string $id): string { return $this->keys[$id]??throw new \RuntimeException('Key unavailable.'); }
    /** @return array<string,string> */
    public function __debugInfo(): array { return ['keys'=>'[REDACTED]']; }
    /** @return never */
    public function __serialize(): array { throw new \LogicException('Keys cannot be serialized.'); }
}
