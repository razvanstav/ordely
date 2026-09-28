<?php

declare(strict_types=1);

namespace Ordely\Tests\Unit;

use Ordely\Adapters\Shopify\{AppConfig,IdTokenVerifier,InvalidIdToken,ShopDomain,WebhookSignature};
use Ordely\Tests\Support\ShopifyFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShopifyAuthenticationTest extends TestCase
{
    public function testValidIdentityDoesNotContainBearerCredentials(): void
    {
        $token = ShopifyFixtures::idToken();
        $identity = (new IdTokenVerifier(ShopifyFixtures::config()))->verify($token);
        self::assertSame('ordely-test.myshopify.com', $identity->shop->value);
        self::assertSame('42', $identity->userId);
        self::assertStringNotContainsString($token, json_encode($identity, JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function rejectedClaims(): iterable
    {
        yield 'expired' => [['exp' => 1]];
        yield 'future nbf' => [['nbf' => PHP_INT_MAX]];
        yield 'future iat' => [['iat' => PHP_INT_MAX]];
        yield 'string timestamp' => [['exp' => '9999999999']];
        yield 'wrong app' => [['aud' => 'another-application']];
        yield 'audience array' => [['aud' => ['synthetic-client-id']]];
        yield 'issuer mismatch' => [['iss' => 'https://another.myshopify.com/admin']];
        yield 'issuer userinfo' => [['iss' => 'https://evil@ordely-test.myshopify.com/admin']];
        yield 'custom domain' => [['dest' => 'https://example.test']];
        yield 'http destination' => [['dest' => 'http://ordely-test.myshopify.com']];
        yield 'destination port' => [['dest' => 'https://ordely-test.myshopify.com:443']];
        yield 'destination path' => [['dest' => 'https://ordely-test.myshopify.com/redirect']];
        yield 'hostname suffix trick' => [['dest' => 'https://ordely-test.myshopify.com.attacker.test']];
        yield 'live shop blocked in dev' => [['dest' => 'https://other-store.myshopify.com', 'iss' => 'https://other-store.myshopify.com/admin']];
        yield 'no user' => [['sub' => null]];
    }

    /** @param array<string,mixed> $claims */
    #[DataProvider('rejectedClaims')]
    public function testRejectsInvalidClaims(array $claims): void
    {
        $this->expectException(InvalidIdToken::class);
        (new IdTokenVerifier(ShopifyFixtures::config()))->verify(ShopifyFixtures::idToken($claims));
    }

    public function testRejectsMalformedTokensAndWrongSignatures(): void
    {
        $verifier = new IdTokenVerifier(ShopifyFixtures::config());
        foreach (['', 'a.b.c', str_repeat('x', 8193), ShopifyFixtures::idToken(header: ['alg' => 'none']),
            ShopifyFixtures::idToken(secret: 'wrong-secret'), ShopifyFixtures::idToken(header: ['alg' => 'HS256', 'crit' => ['custom']])] as $token) {
            try { $verifier->verify($token); self::fail('Rejected identity was accepted.'); }
            catch (InvalidIdToken $error) { self::assertSame('Invalid Shopify identity.', $error->getMessage()); }
        }
    }

    public function testWebhookAuthenticatesRawBytesAndRejectsInvalidMacs(): void
    {
        $config = ShopifyFixtures::config(); $verifier = new WebhookSignature($config);
        $raw = '{"id":42,"name":"Synthetic shop"}';
        $mac = base64_encode(hash_hmac('sha256', $raw, $config->secret(), true));
        self::assertTrue($verifier->valid($raw, $mac));
        self::assertFalse($verifier->valid($raw . "\n", $mac));
        foreach (['', 'invalid', base64_encode(str_repeat('x', 32)), str_repeat('!', 44)] as $bad) {
            self::assertFalse($verifier->valid($raw, $bad));
        }
    }

    public function testConfigDebugAndSerializationDoNotExposeSecret(): void
    {
        $config = ShopifyFixtures::config();
        self::assertStringNotContainsString($config->secret(), print_r($config, true));
        self::assertStringNotContainsString($config->secret(), json_encode($config, JSON_THROW_ON_ERROR));
        $this->expectException(\LogicException::class); serialize($config);
    }

    public function testOnlyCanonicalShopDomainsCanBeUsedForOutboundHttp(): void
    {
        foreach (['localhost', '127.0.0.1', 'shop.myshopify.com@evil.test', 'shop.myshopify.com/path',
            '-shop.myshopify.com', 'shop-.myshopify.com', 'shop.MYSHOPIFY.com', "shop.myshopify.com\n", 'shop.myshopify.com:443'] as $domain) {
            try { new ShopDomain($domain); self::fail('Unsafe host accepted.'); }
            catch (\InvalidArgumentException) { self::addToAssertionCount(1); }
        }
        self::assertSame('https://valid-shop.myshopify.com', (new ShopDomain('valid-shop.myshopify.com'))->origin());
    }
}
