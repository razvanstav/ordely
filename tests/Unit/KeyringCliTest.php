<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Integrations\Infrastructure\KeyRing;
use Ordely\Shared\Id;
use PHPUnit\Framework\TestCase;

final class KeyringCliTest extends TestCase
{
    public function testOperatorCanGenerateAndRotateWithoutPrintingOrOverwritingKeys(): void
    {
        $name='cli-'.substr(Id::new(),0,16);$next=$name.'-next';$root=dirname(__DIR__,2);$first=$root.'/var/keys/'.$name.'.json';$second=$root.'/var/keys/'.$next.'.json';
        try{
            $created=$this->runCommand([$name]);self::assertSame(0,$created['exit']);$json=file_get_contents($first);self::assertIsString($json);$old=KeyRing::fromJson($json);
            $rotated=$this->runCommand([$next,$name]);self::assertSame(0,$rotated['exit']);$newJson=file_get_contents($second);self::assertIsString($newJson);$new=KeyRing::fromJson($newJson);
            self::assertNotSame($old->active,$new->active);self::assertSame($old->key($old->active),$new->key($old->active));
            self::assertStringNotContainsString(base64_encode($old->key($old->active)),$created['output'].$rotated['output']);self::assertStringNotContainsString(base64_encode($new->key($new->active)),$rotated['output']);
            self::assertSame(1,$this->runCommand([$name])['exit']);self::assertSame($json,file_get_contents($first));
            self::assertSame(1,$this->runCommand(['../outside'])['exit']);
            if(PHP_OS_FAMILY!=='Windows'){self::assertSame(0,fileperms($first)&0077);}
        }finally{foreach([$first,$second] as $file){if(is_file($file)){unlink($file);}}}
    }
    /** @param list<string> $arguments
     * @return array{exit:int,output:string} */
    private function runCommand(array $arguments): array
    {
        $process=proc_open([PHP_BINARY,dirname(__DIR__,2).'/bin/keyring.php',...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),options:['bypass_shell'=>true]);
        if(!is_resource($process)){throw new \RuntimeException('CLI unavailable.');}
        fclose($pipes[0]);$output=(string)stream_get_contents($pipes[1]).(string)stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return ['exit'=>proc_close($process),'output'=>$output];
    }
}
