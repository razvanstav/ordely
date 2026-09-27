<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;
use Ordely\Core\Value\{Currency, Money, TaxRate};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testDecimalParsingAndFormattingAreExactAcrossExponents(): void
    {
        foreach ([['RON',2,'129.90',12990], ['JPY',0,'123',123], ['BHD',3,'-0.015',-15], ['RON',2,'90000000000000.00',Money::MAX]] as [$code,$scale,$decimal,$minor]) {
            $money = Money::decimal($decimal, new Currency($code,$scale));
            self::assertSame($minor, $money->minor); self::assertSame($decimal, $money->format());
        }
        self::assertSame(30, Money::decimal('0.10',new Currency('RON',2))->plus(Money::decimal('0.20',new Currency('RON',2)))->minor);
        self::assertStringContainsString('"minor":"9000000000000000"', json_encode(new Money(Money::MAX,new Currency('RON',2)),JSON_THROW_ON_ERROR));
    }

    /** @return iterable<array{string}> */
    public static function malformed(): iterable { foreach (['1.001','1e2','1,00',' 1.00','+1','01.00',"1.00\n",'NaN','-',''] as $value) { yield [$value]; } }
    #[DataProvider('malformed')]
    public function testRejectsMalformedOrOverPreciseDecimals(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class); Money::decimal($value,new Currency('RON',2));
    }

    public function testOverflowIsRejectedBeforePhpCanConvertToFloat(): void
    {
        $ron = new Currency('RON',2);
        foreach ([fn () => Money::decimal('90000000000000.01',$ron), fn () => (new Money(Money::MAX,$ron))->plus(new Money(1,$ron)), fn () => (new Money(Money::MAX,$ron))->times(1000000), fn () => (new Money(Money::MAX,$ron))->ratio(1000000,1)] as $operation) {
            try { $operation(); self::fail('Expected overflow.'); } catch (\OverflowException) { self::addToAssertionCount(1); }
        }
    }

    public function testCurrencyAndExponentMismatchAreRejected(): void
    {
        $money = new Money(100,new Currency('RON',2));
        foreach ([new Currency('EUR',2),new Currency('RON',3)] as $currency) {
            try { $money->plus(new Money(100,$currency)); self::fail('Expected mismatch.'); } catch (\InvalidArgumentException) { self::addToAssertionCount(1); }
        }
    }

    public function testHalfAwayRoundingAndLargeRatiosUseOnlyIntegers(): void
    {
        $ron = new Currency('RON',2);
        self::assertSame(1,(new Money(1,$ron))->ratio(1,2)->minor);
        self::assertSame(-1,(new Money(-1,$ron))->ratio(1,2)->minor);
        self::assertSame(1_890_000_000_000_000,(new TaxRate(2100))->on(new Money(Money::MAX,$ron))->minor);
        self::assertSame(0,(new Money(Money::MAX,$ron))->ratio(0,100)->minor);
    }

    public function testAllocationsPreserveEveryMinorUnitAndStableTieOrder(): void
    {
        $ron = new Currency('RON',2);
        self::assertSame([34,33,33],array_map(fn (Money $m): int => $m->minor,(new Money(100,$ron))->allocate([1,1,1])));
        self::assertSame([-34,-33,-33],array_map(fn (Money $m): int => $m->minor,(new Money(-100,$ron))->allocate([1,1,1])));
        self::assertSame([0,1],array_map(fn (Money $m): int => $m->minor,(new Money(1,$ron))->allocate([0,1])));
        for ($amount=-100; $amount<=100; ++$amount) {
            self::assertSame($amount,array_sum(array_map(fn (Money $m): int => $m->minor,(new Money($amount,$ron))->allocate([2,3,7]))));
        }
        $this->expectException(\InvalidArgumentException::class); (new Money(1,$ron))->allocate([0,0]);
    }
}
