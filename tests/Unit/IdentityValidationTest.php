<?php
declare(strict_types=1);
namespace Ordely\Tests\Unit;

use Ordely\Identity\Application\Passwords;
use Ordely\Shared\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityValidationTest extends TestCase
{
    public function testRandomIdsHaveExpectedShapeAndDoNotRepeat(): void
    {
        $a = Id::new(); self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $a);
        self::assertSame(16, strlen(Id::bytes($a))); self::assertNotSame($a, Id::new());
        $this->expectException(\InvalidArgumentException::class); Id::bytes('../.env');
    }

    /** @return iterable<string,array{string}> */
    public static function invalidPasswords(): iterable
    {
        yield 'short' => ['short']; yield 'bcrypt truncation' => [str_repeat('x', 73)]; yield 'nul' => ["123456789012\0"];
    }

    #[DataProvider('invalidPasswords')]
    public function testPasswordsRejectUnsafeInputs(string $password): void
    {
        $this->expectException(\InvalidArgumentException::class); Passwords::hash($password);
    }
}
