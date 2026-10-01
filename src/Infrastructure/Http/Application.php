<?php

declare(strict_types=1);

namespace Ordely\Infrastructure\Http;

use Closure;
use Ordely\Adapters\Shopify\{ShopifyApi,ShopifyGateway};
use Ordely\Identity\Domain\AccessDenied;
use Ordely\Identity\Presentation\IdentityApi;
use Ordely\Infrastructure\Configuration\Environment;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Operations\Domain\Conflict;
use Ordely\Operations\Presentation\OperationsApi;
use Ordely\Integrations\Presentation\IntegrationsApi;
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
    public function __construct(private Closure $connect,private ?ShopifyGateway $shopifyGateway=null,private ?\Ordely\Integrations\Application\ProviderRegistry $invoiceRegistry=null)
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
        $routes->add('draft_script',new Route('/invoicing.js',methods:['GET']));
        $routes->add('workspace_script',new Route('/workspace.js',methods:['GET']));
        $routes->add('invoice_configuration',new Route('/api/invoice-configuration',methods:['POST']));
        $routes->add('invoice_profile',new Route('/api/invoice-profile',methods:['GET','POST']));
        $routes->add('invoice_preparation',new Route('/api/invoice-preparation/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['GET']));
        $routes->add('order_draft_list',new Route('/api/invoice-order-drafts',methods:['GET']));
        $routes->add('order_draft',new Route('/api/invoice-order-drafts/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['GET','POST','PUT']));
        $routes->add('draft_list',new Route('/api/invoice-drafts',methods:['GET']));
        $routes->add('draft_create',new Route('/api/invoice-drafts',methods:['POST']));
        $routes->add('draft_preview',new Route('/api/invoice-drafts/preview',methods:['POST']));
        $routes->add('draft_get',new Route('/api/invoice-drafts/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['GET']));
        $routes->add('draft_update',new Route('/api/invoice-drafts/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['PUT']));
        $routes->add('draft_archive',new Route('/api/invoice-drafts/{id}/archive',requirements:['id'=>'[a-f0-9]{32}'],methods:['POST']));
        $routes->add('shopify_home',new Route('/shopify',methods:['GET']));
        $routes->add('shopify_script',new Route('/shopify.js',methods:['GET']));
        $routes->add('shopify_connect',new Route('/shopify/connect',methods:['POST']));
        $routes->add('shopify_status',new Route('/shopify/status',methods:['GET']));
        $routes->add('shopify_webhook',new Route('/webhooks/shopify',methods:['POST']));
        $routes->add('shopify_intent',new Route('/api/shopify/intents',methods:['POST']));
        $routes->add('shopify_check',new Route('/api/shopify/check',methods:['POST']));
        $routes->add('shopify_events',new Route('/api/shopify/events',methods:['GET']));
        $routes->add('commerce_start',new Route('/api/commerce/import',methods:['POST']));
        $routes->add('commerce_list',new Route('/api/commerce',methods:['GET']));
        $routes->add('commerce_order',new Route('/api/commerce/orders/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['GET']));
        $routes->add('commerce_privacy',new Route('/api/commerce/privacy/{id}',requirements:['id'=>'[a-f0-9]{32}'],methods:['POST']));
        $routes->add('login', new Route('/api/auth/login', methods: ['POST']));
        $routes->add('logout', new Route('/api/auth/logout', methods: ['POST']));
        $routes->add('switch', new Route('/api/auth/merchant', methods: ['POST']));
        $routes->add('me', new Route('/api/me', methods: ['GET']));
        $routes->add('stores', new Route('/api/stores', methods: ['GET']));
        $routes->add('create_store', new Route('/api/stores', methods: ['POST']));
        $routes->add('get_store', new Route('/api/stores/{id}', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET']));
        $routes->add('rename_store', new Route('/api/stores/{id}', requirements: ['id' => '[a-f0-9]{32}'], methods: ['PATCH']));
        $routes->add('ops_list',new Route('/api/operations',methods:['GET']));
        $routes->add('ops_retry',new Route('/api/jobs/{id}/retry',requirements:['id'=>'[a-f0-9]{32}'],methods:['POST']));
        $routes->add('ops_confirm',new Route('/api/operations/{id}/confirm',requirements:['id'=>'[a-f0-9]{32}'],methods:['POST']));
        $routes->add('integrations_list',new Route('/api/integrations',methods:['GET']));
        $routes->add('integrations_create',new Route('/api/integrations',methods:['POST']));
        foreach(['rotate','credentials','revoke','bind','unbind','capabilities'] as $action){$routes->add('integrations_'.$action,new Route('/api/integrations/{id}/'.$action,requirements:['id'=>'[a-f0-9]{32}'],methods:['POST']));}
        $context = (new RequestContext())->fromRequest($request);

        try {
            $route = (new UrlMatcher($routes, $context))->match($request->getPathInfo());
            if (Environment::string('APP_ENV', 'dev') === 'prod' && !$request->isSecure()) { throw new Problem(400, 'https_required'); }
            $name = (string) $route['_route'];
            if($name==='home'&&$request->query->has('shop')){$name='shopify_home';}
            $response = match ($name) {
                'order_draft_list','order_draft'=>(new \Ordely\Invoicing\Presentation\OrderDraftApi(new Sql(($this->connect)())))->handle($name,$request,isset($route['id'])?(string)$route['id']:null),
                'invoice_preparation'=>(new \Ordely\Invoicing\Presentation\OrderPreparationApi(new Sql(($this->connect)())))->handle($request,(string)$route['id']),
                'invoice_configuration'=>(new \Ordely\Invoicing\Presentation\InvoiceConfigurationApi(new Sql(($this->connect)()),$this->invoiceRegistry))->handle($request),
                'invoice_profile'=>(new \Ordely\Invoicing\Presentation\InvoiceProfileApi(new Sql(($this->connect)()),$this->invoiceRegistry))->handle($request),
                'draft_list','draft_get','draft_create','draft_update','draft_archive','draft_preview'=>(new \Ordely\Invoicing\Presentation\DraftApi(new Sql(($this->connect)())))->handle($name,$request,isset($route['id'])?(string)$route['id']:null),
                'draft_script'=>$this->asset('invoicing.js','text/javascript'),
                'workspace_script'=>$this->asset('workspace.js','text/javascript'),
                'commerce_start','commerce_list','commerce_order','commerce_privacy'=>(new \Ordely\Commerce\Presentation\CommerceApi(new Sql(($this->connect)())))->handle($name,$request,isset($route['id'])?(string)$route['id']:null),
                'health' => new JsonResponse(['status' => 'ok']),
                'ready' => $this->readiness(),
                'home' => $this->asset('index.html', 'text/html'),
                'script' => $this->asset('app.js', 'text/javascript'),
                'style' => $this->asset('app.css', 'text/css'),
                'shopify_script'=>$this->asset('shopify.js','text/javascript'),
                'shopify_home','shopify_connect','shopify_status','shopify_webhook','shopify_intent','shopify_check','shopify_events'=>(new ShopifyApi(new Sql(($this->connect)()),$this->shopifyGateway))->handle($name,$request),
                'ops_list','ops_retry','ops_confirm'=>(new OperationsApi(new Sql(($this->connect)())))->handle($name,$request,isset($route['id'])?(string)$route['id']:null),
                'integrations_list','integrations_create','integrations_rotate','integrations_credentials','integrations_revoke','integrations_bind','integrations_unbind','integrations_capabilities'=>(new IntegrationsApi(new Sql(($this->connect)())))->handle($name,$request,isset($route['id'])?(string)$route['id']:null),
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
        } catch (Conflict) {
            $response = new JsonResponse(['error' => 'conflict'], 409);
        } catch (\InvalidArgumentException) {
            $response = new JsonResponse(['error' => 'invalid_input'], 400);
        } catch (Throwable $error) {
            error_log('Ordely request failure: ' . $error::class);
            $response = new JsonResponse(['error' => 'internal_error'], 500);
        }

        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if(!$response->headers->has('Content-Security-Policy')){$response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");}
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
