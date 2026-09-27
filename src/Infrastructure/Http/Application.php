<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Http;

use Closure;
use PDO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

final readonly class Application
{
    /** @param Closure(): PDO $connect */
    public function __construct(private Closure $connect)
    {
    }

    public function handle(Request $request): Response
    {
        $routes = new RouteCollection();
        $routes->add('health', new Route('/health', methods: ['GET']));
        $routes->add('ready', new Route('/ready', methods: ['GET']));
        $context = (new RequestContext())->fromRequest($request);

        try {
            $route = (new UrlMatcher($routes, $context))->match($request->getPathInfo());
            $response = $route['_route'] === 'ready'
                ? $this->readiness()
                : new JsonResponse(['status' => 'ok']);
        } catch (ResourceNotFoundException) {
            $response = new JsonResponse(['error' => 'not_found'], 404);
        } catch (MethodNotAllowedException $exception) {
            $response = new JsonResponse(['error' => 'method_not_allowed'], 405, [
                'Allow' => implode(', ', $exception->getAllowedMethods()),
            ]);
        }

        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response->prepare($request);
    }

    private function readiness(): Response
    {
        try {
            ($this->connect)()->query('SELECT 1');

            return new JsonResponse(['status' => 'ready']);
        } catch (Throwable) {
            // Never expose connection strings, credentials or provider diagnostics in public health endpoints.
            return new JsonResponse(['status' => 'unavailable'], 503);
        }
    }
}
