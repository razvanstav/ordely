<?php
declare(strict_types=1);
namespace Ordely\Identity\Presentation;

use Ordely\Identity\Infrastructure\{Sessions, StoreRepository};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Core\Value\OperationKey;
use Ordely\Operations\Domain\SafePayload;
use Ordely\Operations\Infrastructure\IdempotencyGate;
use Symfony\Component\HttpFoundation\{Cookie, JsonResponse, Request, Response};

final readonly class IdentityApi
{
    public const COOKIE = 'ordely_session';
    public function __construct(private Sql $db) {}

    public function handle(string $route, Request $request, ?string $id = null): Response
    {
        $sessions = new Sessions($this->db);
        $guard=new SessionGuard($this->db);
        if ($route === 'login') {
            $guard->validateWrite($request);
            $data = self::body($request);
            $token = $sessions->login(self::field($data, 'email', 254), self::field($data, 'password', 72), $request->getClientIp() ?? 'unknown');
            return $this->withCookie(new JsonResponse(['csrf' => Sessions::csrf($token)]), $token, $request);
        }
        $token = $request->cookies->get(self::COOKIE, '');
        $context = $guard->context($request);

        $stores = new StoreRepository($this->db);
        switch ($route) {
            case 'me': return new JsonResponse(['merchantId' => $context->merchantId, 'role' => $context->role->value, 'allStores'=>$context->allStores, 'csrf' => Sessions::csrf($token), 'merchants' => $sessions->merchants($context->userId)]);
            case 'logout':
                $sessions->revoke($token);
                $response = new JsonResponse(['status' => 'logged_out']);
                $response->headers->clearCookie(self::COOKIE, '/', secure: $request->isSecure(), httpOnly: true, sameSite: 'lax');
                return $response;
            case 'switch':
                $new = $sessions->switchMerchant($token, self::field(self::body($request), 'merchantId', 32));
                return $this->withCookie(new JsonResponse(['csrf' => Sessions::csrf($new)]), $new, $request);
            case 'stores': return new JsonResponse(['stores' => $stores->list($context)]);
            case 'create_store':
                $data = self::body($request);
                $name=self::field($data,'name',160);$platform=self::field($data,'platform',40);
                $header=$request->headers->get('Idempotency-Key');
                if($header===null){throw new Problem(400,'idempotency_key_required');}
                $result=(new IdempotencyGate($this->db))->run($context,null,'stores.create','stores.manage',new OperationKey($header),['name'=>$name,'platform'=>$platform],
                    fn():SafePayload=>new SafePayload(['store_id'=>$stores->create($context,$name,$platform)]));
                return new JsonResponse(['id' => $result->id('store_id')], 201);
            case 'get_store':
                $store = $stores->get($context, $id ?? '');
                if ($store === null) { throw new Problem(404, 'not_found'); }
                return new JsonResponse($store);
            case 'rename_store':
                if (!$stores->rename($context, $id ?? '', self::field(self::body($request), 'name', 160))) { throw new Problem(404, 'not_found'); }
                return new JsonResponse(['status' => 'updated']);
            default: throw new Problem(404, 'not_found');
        }
    }

    /** @return array<string,mixed> */
    public static function body(Request $request): array
    {
        $content = $request->getContent();
        if (strlen($content) > 16384) { throw new Problem(413, 'body_too_large'); }
        try { $data = json_decode($content, false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new Problem(400, 'invalid_json'); }
        if (!$data instanceof \stdClass) { throw new Problem(400, 'object_required'); }
        return get_object_vars($data);
    }

    /** @param array<string,mixed> $data */
    public static function field(array $data, string $key, int $maximum): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '' || mb_strlen($value) > $maximum) { throw new Problem(400, 'invalid_input'); }
        return $value;
    }

    private function withCookie(JsonResponse $response, #[\SensitiveParameter] string $token, Request $request): Response
    {
        $response->headers->setCookie(Cookie::create(self::COOKIE)->withValue($token)->withExpires(time() + 28800)->withPath('/')->withSecure($request->isSecure())->withHttpOnly(true)->withSameSite('lax'));
        return $response;
    }
}
