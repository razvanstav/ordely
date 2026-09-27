<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;

final class ContractFixtures
{
    public static function context(): ConnectionContext { return new ConnectionContext(V\MerchantId::new(),V\StoreId::new(),V\ConnectionId::new(),V\CorrelationId::new()); }
    public static function money(int $minor): V\Money { return new V\Money($minor,new V\Currency('RON',2)); }
    public static function address(): V\Address { return new V\Address('Test recipient','RO','Test city','Test street 1','000000'); }
    public static function customer(): D\CustomerSnapshot { return new D\CustomerSnapshot(new V\ExternalId('customer-1'),'Test customer',new V\EmailAddress('customer@example.test'),self::address(),self::address()); }
    public static function line(): D\CommercialLine { return new D\CommercialLine(new V\ExternalId('line-1'),'Test product',new V\Quantity(2),self::money(5000),self::money(1000),self::money(1890)); }
    public static function order(string $id='order-1'): D\OrderSnapshot { return new D\OrderSnapshot(new V\ExternalId($id),'1001',[self::line()],self::customer(),self::money(1900),self::money(12790),self::money(0),new \DateTimeImmutable('2026-01-01T12:00:00Z')); }
    /** @return list<D\LineAllocation> */
    public static function allocations(): array { return [new D\LineAllocation(new V\ExternalId('line-1'),new V\Quantity(1))]; }
    public static function tracking(): D\TrackingUpdate { return new D\TrackingUpdate(new V\ExternalId('TEST-123'),'fake','https://example.test/TEST-123'); }
    public static function shipment(int $cod=12790,bool $exchange=false): D\ShipmentRequest
    {
        return new D\ShipmentRequest(self::address(),self::address(),[new V\Parcel(500,100,100,100)],self::allocations(),new V\ExternalId('standard'),self::money($cod),self::money(12790),new V\OperationKey('shipment-business-1'),$exchange);
    }
    public static function invoice(): D\InvoiceDraft
    {
        return new D\InvoiceDraft(new D\CompanyDetails('Test seller','TEST000',self::address()),self::customer(),[self::line()],self::money(10890),new V\ExternalId('TEST'),new V\OperationKey('invoice-business-1'));
    }
}
