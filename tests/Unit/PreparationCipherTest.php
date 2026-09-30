<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Invoicing\Infrastructure\PreparationCipher;
use Ordely\Tests\Support\{IntegrationFixtures,PreparationFixtures};
use Ordely\Shared\Id;
use PHPUnit\Framework\TestCase;
final class PreparationCipherTest extends TestCase
{
    public function testLargeSnapshotAndEveryContextBinding(): void
    {
        $cipher=new PreparationCipher(IntegrationFixtures::cipher());$ids=[Id::new(),Id::new(),Id::new()];
        $data=PreparationFixtures::order();$data['padding']=str_repeat('Synthetic private data ',5000);
        $envelope=$cipher->seal(...[...$ids,3,0,1,$data]);
        self::assertSame(\Ordely\Core\Value\CanonicalJson::encode($data),\Ordely\Core\Value\CanonicalJson::encode($cipher->open(...[...$ids,3,0,1,$envelope])));self::assertStringNotContainsString('private',$envelope);
        $contexts=[[Id::new(),$ids[1],$ids[2],3,0,1],[$ids[0],Id::new(),$ids[2],3,0,1],[$ids[0],$ids[1],Id::new(),3,0,1],[...$ids,4,0,1],[...$ids,3,1,1],[...$ids,3,0,2]];
        foreach($contexts as $context){
            try{$cipher->open(...[...$context,$envelope]);self::fail('Changed context accepted.');}catch(\RuntimeException $error){self::assertSame('Invoice preparation unavailable.',$error->getMessage());}}
        $parts=json_decode($envelope,true,flags:JSON_THROW_ON_ERROR);array_pop($parts['chunks']);
        $this->expectException(\RuntimeException::class);$cipher->open(...[...$ids,3,0,1,json_encode($parts,JSON_THROW_ON_ERROR)]);
    }
}
