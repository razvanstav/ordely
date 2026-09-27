<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Core\Contracts\{ConnectionContext,Page};
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
use Ordely\Tests\Support\ContractFixtures as F;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoreValidationTest extends TestCase
{
    public function testCanonicalPayloadSortsObjectKeysPreservesListOrderAndRejectsFloats(): void
    {
        self::assertSame(V\CanonicalJson::encode(['b'=>2,'a'=>1]),V\CanonicalJson::encode(['a'=>1,'b'=>2]));
        self::assertNotSame(V\CanonicalJson::encode([1,2]),V\CanonicalJson::encode([2,1]));
        self::assertSame(V\CanonicalJson::encode(new \DateTimeImmutable('2026-01-01T12:00:00Z')),V\CanonicalJson::encode(new \DateTimeImmutable('2026-01-01T14:00:00+02:00')));
        self::assertSame('"created"',V\CanonicalJson::encode(D\ShipmentState::Created));
        $this->expectException(\InvalidArgumentException::class);V\CanonicalJson::encode(['amount'=>1.0]);
    }
    public function testIdentifierTypesCannotBeInterchanged(): void
    {
        $this->expectException(\TypeError::class);
        (new \ReflectionClass(ConnectionContext::class))->newInstanceArgs([V\StoreId::new(),V\StoreId::new(),V\ConnectionId::new(),V\CorrelationId::new()]);
    }
    /** @return iterable<string,array{\Closure():mixed}> */
    public static function invalid(): iterable
    {
        yield 'quantity zero'=>[fn()=>new V\Quantity(0)];
        yield 'parcel units'=>[fn()=>new V\Parcel(0,1,1,1)];
        yield 'page size'=>[fn()=>new V\PageRequest(101)];
        yield 'cursor size'=>[fn()=>new V\PageRequest(1,str_repeat('x',2049))];
        yield 'external ref NUL'=>[fn()=>new V\ExternalId("ref\0")];
        yield 'key with email'=>[fn()=>new V\OperationKey('person@example.test')];
        yield 'untrusted tracking URL'=>[fn()=>new D\TrackingUpdate(new V\ExternalId('123'),'fake','javascript:alert(1)')];
        yield 'arbitrary metadata'=>[fn()=>new D\OrderMetadata(['password'=>'secret'])];
        yield 'address country'=>[fn()=>new V\Address('Test','romania','City','Line','000000')];
        yield 'discount over net'=>[fn()=>new D\CommercialLine(new V\ExternalId('1'),'Test',new V\Quantity(1),F::money(100),F::money(101),F::money(0))];
        yield 'wrong order total'=>[fn()=>new D\OrderSnapshot(new V\ExternalId('1'),'1',[F::line()],F::customer(),F::money(0),F::money(1),F::money(0),new \DateTimeImmutable())];
        yield 'wrong invoice total'=>[fn()=>new D\InvoiceDraft(F::invoice()->seller,F::customer(),[F::line()],F::money(1),new V\ExternalId('TEST'),new V\OperationKey('invoice'))];
        yield 'duplicate fulfillment line'=>[fn()=>new D\FulfillmentRequest(new V\ExternalId('1'),[...F::allocations(),...F::allocations()],new V\ExternalId('location'),F::tracking())];
        yield 'invalid subscription'=>[fn()=>new D\WebhookSubscription('anything','https://example.test/hook')];
        yield 'invalid result page'=>[fn()=>new Page(array_fill(0,101,'x'))];
    }
    #[DataProvider('invalid')]
    public function testInvalidSnapshotsAndValuesAreRejected(\Closure $create): void { $this->expectException(\InvalidArgumentException::class);$create(); }
}
