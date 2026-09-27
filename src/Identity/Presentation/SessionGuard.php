<?php
declare(strict_types=1);
namespace Ordely\Identity\Presentation;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Identity\Infrastructure\Sessions;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Symfony\Component\HttpFoundation\Request;

final readonly class SessionGuard
{
    public function __construct(private Sql $db) {}
    public function validateWrite(Request $request): void
    {
        if(in_array($request->getMethod(),['GET','HEAD'],true)){return;}
        $origin=$request->headers->get('Origin');
        if($origin!==null&&$origin!==$request->getSchemeAndHttpHost()){throw new Problem(403,'invalid_origin');}
        if(trim(explode(';',strtolower($request->headers->get('Content-Type') ?? ''))[0])!=='application/json'){throw new Problem(415,'json_required');}
    }
    public function context(Request $request): TenantContext
    {
        $this->validateWrite($request);$token=$request->cookies->get(IdentityApi::COOKIE,'');
        $context=(new Sessions($this->db))->resolve($token);
        if(!in_array($request->getMethod(),['GET','HEAD'],true)&&!hash_equals(Sessions::csrf($token),$request->headers->get('X-CSRF-Token') ?? '')){throw new Problem(403,'invalid_csrf');}
        return $context;
    }
}
