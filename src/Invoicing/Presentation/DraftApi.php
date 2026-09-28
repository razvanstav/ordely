<?php
declare(strict_types=1);
namespace Ordely\Invoicing\Presentation;

use Ordely\Identity\Presentation\{IdentityApi,SessionGuard};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Infrastructure\Http\Problem;
use Ordely\Integrations\Infrastructure\{KeyRing,SecretCipher};
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Invoicing\Infrastructure\{DraftCipher,DraftRepository};
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};

final readonly class DraftApi
{
    public function __construct(private Sql $db) {}
    public function handle(string $route,Request $request,?string $id): Response
    {
        $actor=(new SessionGuard($this->db))->context($request);
        if($request->isMethod('GET')){
            $store=$request->query->getString('storeId');(new AccessPolicy($this->db))->require($actor,'invoices.read',$store);
            $repository=$this->repository();
            return new JsonResponse($route==='draft_get'?$repository->get($actor,$store,$id??''):$repository->list($actor,$store,$request->query->getString('status','DRAFT'),$request->query->has('after')?$request->query->getString('after'):null));
        }
        $body=IdentityApi::body($request);$store=IdentityApi::field($body,'storeId',32);(new AccessPolicy($this->db))->require($actor,'invoices.draft',$store);
        if($route==='draft_preview'){
            return new JsonResponse(['totals'=>$this->document($body)->totals(),'status'=>'DRAFT']);
        }
        $repository=$this->repository();
        if($route==='draft_create'){
            $id=$repository->create($actor,$store,IdentityApi::field($body,'id',32),$this->document($body));return new JsonResponse(['id'=>$id],201);
        }
        $version=$body['version']??null;if(!is_int($version)||$version<1){throw new Problem(400,'invalid_version');}
        if($route==='draft_update'){$repository->replace($actor,$store,$id??'',$version,$this->document($body));}
        elseif($route==='draft_archive'){$repository->archive($actor,$store,$id??'',$version);}
        else{throw new Problem(404,'not_found');}
        return new JsonResponse(['status'=>'updated']);
    }
    private function repository(): DraftRepository { return new DraftRepository($this->db,new DraftCipher(new SecretCipher(KeyRing::fromEnvironment()))); }
    /** @param array<string,mixed> $body */
    private function document(#[\SensitiveParameter] array $body): DraftDocument
    {
        if(!($body['document']??null) instanceof \stdClass){throw new Problem(400,'invalid_document');}
        try{$data=json_decode(json_encode($body['document'],JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);}
        catch(\JsonException){throw new Problem(400,'invalid_document');}
        return DraftDocument::fromArray($data);
    }
}
