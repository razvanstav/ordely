<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    /** @return list<string> */
    private function forbidden(string $source): array
    {
        $bad=[];
        foreach(token_get_all($source,TOKEN_PARSE) as $token){
            if(!is_array($token)){continue;}
            if(in_array($token[0],[T_REQUIRE,T_REQUIRE_ONCE,T_INCLUDE,T_INCLUDE_ONCE],true)){$bad[]=$token[1];}
            if(in_array($token[0],[T_NAME_QUALIFIED,T_NAME_FULLY_QUALIFIED],true)){
                $name=ltrim($token[1],'\\');
                if(str_contains($name,'\\') && !str_starts_with($name,'Ordely\\Core\\') && !str_starts_with($name,'D\\') && !str_starts_with($name,'V\\')){$bad[]=$name;}
            }
            if($token[0]===T_STRING && in_array($token[1],['PDO','mysqli','curl_init','file_get_contents','fopen','getenv','eval'],true)){$bad[]=$token[1];}
        }
        return $bad;
    }
    public function testCoreHasNoInfrastructureProviderOrSdkDependencies(): void
    {
        $root=dirname(__DIR__,2).'/src/Core';$checked=0;
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file){
            if(!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension()!=='php'){continue;}
            $source=file_get_contents($file->getPathname());self::assertIsString($source);
            self::assertSame([],$this->forbidden($source),$file->getPathname());++$checked;
        }
        self::assertGreaterThan(20,$checked);
    }
    public function testBoundaryCheckDetectsNewSdkAndDatabaseImports(): void
    {
        self::assertNotEmpty($this->forbidden('<?php use Vendor\\Sdk\\Client;'));
        self::assertNotEmpty($this->forbidden('<?php use Ordely\\Adapters\\Fake\\FakeCarrier;'));
        self::assertNotEmpty($this->forbidden('<?php new PDO("dsn");'));
        self::assertSame([],$this->forbidden('<?php use Ordely\\Core\\Contracts\\CarrierProvider;'));
    }
}
