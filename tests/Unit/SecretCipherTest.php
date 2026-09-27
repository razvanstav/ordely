<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Shared\Id;
use PHPUnit\Framework\TestCase;

final class SecretCipherTest extends TestCase
{
    public function testAuthenticatedRoundTripUsesUniqueNoncesAndRedactsObjects(): void
    {
        $key=random_bytes(32);$cipher=new SecretCipher(new KeyRing('first',['first'=>$key]));$merchant=Id::new();$connection=Id::new();$secret=new Secrets(['apiToken'=>'SYNTHETIC-secret-token']);
        $first=$cipher->encrypt($merchant,$connection,'fake-carrier',$secret);$second=$cipher->encrypt($merchant,$connection,'fake-carrier',$secret);
        self::assertNotSame($first,$second);self::assertStringNotContainsString('SYNTHETIC-secret-token',$first);
        self::assertSame($secret->reveal(),$cipher->decrypt($merchant,$connection,'fake-carrier',$first)->reveal());
        self::assertSame('"[REDACTED]"',json_encode($secret));
        ob_start();var_dump($secret,new KeyRing('first',['first'=>$key]));$dump=(string)ob_get_clean();
        self::assertStringNotContainsString('SYNTHETIC-secret-token',$dump);self::assertStringNotContainsString($key,$dump);
        $this->expectException(\LogicException::class);serialize($secret);
    }
    public function testTamperingTruncatedTagsAndContextSwappingFailClosed(): void
    {
        $cipher=new SecretCipher(new KeyRing('first',['first'=>random_bytes(32)]));$merchant=Id::new();$connection=Id::new();$sealed=$cipher->encrypt($merchant,$connection,'fake-carrier',new Secrets(['apiToken'=>'sensitive-test-value']));
        $original=json_decode($sealed,true,flags:JSON_THROW_ON_ERROR);
        $variants=[];
        foreach(['nonce','tag','cipher'] as $part){$tampered=$original;$bytes=base64_decode($tampered[$part]);$bytes[0]=chr(ord($bytes[0])^1);$tampered[$part]=base64_encode($bytes);$variants[]=json_encode($tampered,JSON_THROW_ON_ERROR);}
        foreach([0,1,4,15,17] as $length){$tampered=$original;$tampered['tag']=base64_encode(str_repeat('x',$length));$variants[]=json_encode($tampered,JSON_THROW_ON_ERROR);}
        foreach(['key'=>'missing','v'=>2,'nonce'=>'%%%','cipher'=>''] as $field=>$value){$tampered=$original;$tampered[$field]=$value;$variants[]=json_encode($tampered,JSON_THROW_ON_ERROR);}
        foreach($variants as $variant){$this->rejected(fn()=>$cipher->decrypt($merchant,$connection,'fake-carrier',$variant));}
        $this->rejected(fn()=>$cipher->decrypt(Id::new(),$connection,'fake-carrier',$sealed));
        $this->rejected(fn()=>$cipher->decrypt($merchant,Id::new(),'fake-carrier',$sealed));
        $this->rejected(fn()=>$cipher->decrypt($merchant,$connection,'fake-invoice',$sealed));
    }
    public function testKeyRotationRetainsOldDecryptCapabilityUntilMigration(): void
    {
        $old=random_bytes(32);$new=random_bytes(32);$a=new SecretCipher(new KeyRing('old',['old'=>$old]));$b=new SecretCipher(new KeyRing('new',['old'=>$old,'new'=>$new]));$newOnly=new SecretCipher(new KeyRing('new',['new'=>$new]));
        $merchant=Id::new();$connection=Id::new();$envelope=$a->encrypt($merchant,$connection,'fake-carrier',new Secrets(['apiToken'=>'rotation-token']));
        $migrated=$b->encrypt($merchant,$connection,'fake-carrier',$b->decrypt($merchant,$connection,'fake-carrier',$envelope));
        self::assertSame(['apiToken'=>'rotation-token'],$newOnly->decrypt($merchant,$connection,'fake-carrier',$migrated)->reveal());
        $this->rejected(fn()=>$newOnly->decrypt($merchant,$connection,'fake-carrier',$envelope));
        $wrong=new SecretCipher(new KeyRing('old',['old'=>random_bytes(32)]));$this->rejected(fn()=>$wrong->decrypt($merchant,$connection,'fake-carrier',$envelope));
    }
    public function testInvalidKeyMaterialCannotBeSilentlyPaddedOrTruncated(): void
    {
        foreach([0,16,31,33,64] as $length){$this->rejected(fn()=>new KeyRing('key',['key'=>str_repeat('a',$length)]));}
        foreach(['{}','{"active":"key","keys":{"key":"%%%"}}','{"active":"missing","keys":{"key":"'.base64_encode(random_bytes(32)).'"}}'] as $json){$this->rejected(fn()=>KeyRing::fromJson($json));}
        $this->expectException(\LogicException::class);serialize(new KeyRing('key',['key'=>random_bytes(32)]));
    }
    private function rejected(\Closure $call): void
    {
        try{$call();self::fail('Expected closed failure.');}catch(\RuntimeException $error){self::assertStringNotContainsString('sensitive-test-value',$error->getMessage());self::assertNull($error->getPrevious());}
    }
}
