<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Core\Contracts\{CommerceConnector,CarrierProvider,InvoiceProvider};
use Ordely\Integrations\Application\{ProviderDefinition,ProviderRegistry};
use Ordely\Integrations\Domain\{ProviderKind,Secrets};
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    public function testRegistryExposesPlannedProvidersWithoutClaimingConnectivityAndDisablesFakesInProduction(): void
    {
        $before=$_ENV['APP_ENV']??null;
        try{
            $_ENV['APP_ENV']='test';$registry=$this->registry();$secret=new Secrets(['apiToken'=>'test-value']);
            self::assertInstanceOf(CommerceConnector::class,$registry->get('fake-commerce')->build($secret));
            self::assertInstanceOf(CarrierProvider::class,$registry->get('fake-carrier')->build($secret));
            self::assertInstanceOf(InvoiceProvider::class,$registry->get('fake-invoice')->build($secret));
            foreach(['shopify','sameday','fan'] as $key){self::assertFalse($registry->get($key)->available());}
            self::assertTrue($registry->get('oblio')->available());self::assertInstanceOf(InvoiceProvider::class,$registry->get('oblio')->build(\Ordely\Tests\Support\OblioFixtures::credentials()));
            $_ENV['APP_ENV']='prod';$production=$this->registry();self::assertCount(4,$production->metadata());
            $this->expectException(\InvalidArgumentException::class);$production->get('fake-carrier');
        }finally{if($before===null){unset($_ENV['APP_ENV']);}else{$_ENV['APP_ENV']=$before;}}
    }
    public function testDuplicateRegistrationsFail(): void
    {
        $definition=new ProviderDefinition('test','Test',ProviderKind::Commerce);
        $this->expectException(\LogicException::class);new ProviderRegistry($definition,$definition);
    }
    public function testUnavailableProviderRejectsCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);$this->registry()->get('shopify')->build(new Secrets(['apiToken'=>'not-real']));
    }
    private function registry(): ProviderRegistry { return require dirname(__DIR__,2).'/config/providers.php'; }
}
