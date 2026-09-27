<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Http;

use Closure;
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Identity\Presentation\IdentityApi;
use Ordely\Infrastructure\Configuration\Environment;
use Ordely\Infrastructure\Database\Sql;
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
        $routes->add('home', new Route('/', methods: ['GET']));
        $routes->add('script', new Route('/app.js', methods: ['GET']));
        $routes->add('style', new Route('/app.css', methods: ['GET']));
        $routes->add('login', new Route('/api/auth/login', methods: ['POST']));
        $routes->add('logout', new Route('/api/auth/logout', methods: ['POST']));
        $routes->add('switch', new Route('/api/auth/merchant', methods: ['POST']));
        $routes->add('me', new Route('/api/me', methods: ['GET']));
        $routes->add('stores', new Route('/api/stores', methods: ['GET']));
        $routes->add('create_store', new Route('/api/stores', methods: ['POST']));
        $routes->add('get_store', new Route('/api/stores/{id}', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET']));
        $routes->add('rename_store', new Route('/api/stores/{id}', requirements: ['id' => '[a-f0-9]{32}'], methods: ['PATCH']));
        $context = (new RequestContext())->fromRequest($request);

        try {
            $route = (new UrlMatcher($routes, $context))->match($request->getPathInfo());
            if (Environment::string('APP_ENV', 'dev') === 'prod' && !$request->isSecure()) { throw new Problem(400, 'https_required'); }
            $name = (string) $route['_route'];
            $response = match ($name) {
                'health' => new JsonResponse(['status' => 'ok']),
                'ready' => $this->readiness(),
                'home' => $this->asset('index.html', 'text/html'),
                'script' => $this->asset('app.js', 'text/javascript'),
                'style' => $this->asset('app.css', 'text/css'),
                default => (new IdentityApi(new Sql(($this->connect)())))->handle($name, $request, isset($route['id']) ? (string) $route['id'] : null),
            };
        } catch (ResourceNotFoundException) {
            $response = new JsonResponse(['error' => 'not_found'], 404);
        } catch (MethodNotAllowedException $exception) {
            $response = new JsonResponse(['error' => 'method_not_allowed'], 405, [
                'Allow' => implode(', ', $exception->getAllowedMethods()),
            ]);
        } catch (Problem $problem) {
            $response = new JsonResponse(['error' => $problem->getMessage()], $problem->status);
        } catch (AccessDenied) {
            $response = new JsonResponse(['error' => 'forbidden'], 403);
        } catch (\InvalidArgumentException) {
            $response = new JsonResponse(['error' => 'invalid_input'], 400);
        } catch (Throwable $error) {
            error_log('Ordely request failure: ' . $error::class);
            $response = new JsonResponse(['error' => 'internal_error'], 500);
        }

        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response->prepare($request);
    }

    private function asset(string $file, string $type): Response
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/resources/' . $file);
        if ($content === false) { throw new \RuntimeException('Asset unavailable.'); }
        return new Response($content, 200, ['Content-Type' => $type . '; charset=UTF-8']);
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
