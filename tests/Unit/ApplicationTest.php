<?php

declare(strict_types=1);

namespace Ordely\Tests\Unit;

use Ordely\Infrastructure\Http\Application;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class ApplicationTest extends TestCase
{
    private function offlineApplication(): Application
    {
        return new Application(static function (): PDO {
            throw new RuntimeException('mysql:host=private-host; password=do-not-expose');
        });
    }

    public function testLivenessDoesNotRequireTheDatabase(): void
    {
        $response = $this->offlineApplication()->handle(Request::create('/health'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testReadinessFailsClosedWithoutLeakingDatabaseDetails(): void
    {
        $response = $this->offlineApplication()->handle(Request::create('/ready'));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('{"status":"unavailable"}', $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testUnsupportedMethodsAreRejected(): void
    {
        $response = $this->offlineApplication()->handle(Request::create('/health', 'POST'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    public function testUnknownPathsDoNotExposeProjectFiles(): void
    {
        $response = $this->offlineApplication()->handle(Request::create('/.env'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"not_found"}', $response->getContent());
    }

    public function testHeadReturnsHeadersWithoutBody(): void
    {
        $response = $this->offlineApplication()->handle(Request::create('/health', 'HEAD'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }
}
