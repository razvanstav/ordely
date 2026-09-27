<?php
declare(strict_types=1);
namespace Ordely\Operations\Presentation;
use Ordely\Core\Value\ExternalId;
use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Operations\Infrastructure\{ExternalOperations,MySqlJobQueue,OperationsQuery};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class OperationsApi
{
    public function __construct(private Sql $db) {}
    public function handle(string $route,Request $request,?string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);
        if($route==='ops_list'){return new JsonResponse((new OperationsQuery($this->db))->read($actor));}
        if($route==='ops_retry'){
            (new MySqlJobQueue($this->db))->requeue($actor,$id ?? '');return new JsonResponse(['status'=>'queued']);
        }
        if($route==='ops_confirm'){
            $body=IdentityApi::body($request);$version=$body['version'] ?? null;
            if(!is_int($version)||$version<1){throw new Problem(400,'invalid_version');}
            $result=(new ExternalOperations($this->db))->confirmReconciled($actor,$id ?? '',$version,new ExternalId(IdentityApi::field($body,'reference',255)),IdentityApi::field($body,'evidenceHash',64));
            return new JsonResponse(['id'=>$result->id,'status'=>$result->state->value,'version'=>$result->version]);
        }
        throw new Problem(404,'not_found');
    }
}
