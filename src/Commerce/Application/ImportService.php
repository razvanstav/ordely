<?php
declare(strict_types=1);
namespace Ordely\Commerce\Application;

use Ordely\Core\Contracts\{ConnectionContext,ImportSource};
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId,OperationKey};
use Ordely\Commerce\Infrastructure\ImportRepository;
use Ordely\Identity\Domain\{AccessDenied,TenantContext};
use Ordely\Identity\Infrastructure\AccessPolicy;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Infrastructure\ConnectionGuard;
use Ordely\Integrations\Domain\ProviderKind;
use Ordely\Operations\Application\JobHandler;
use Ordely\Operations\Domain\{Actor,AuditAction,Conflict,JobLease,SafePayload,Scope};
use Ordely\Operations\Infrastructure\{AuditLog,MySqlJobQueue};
use Ordely\Shared\Id;

final readonly class ImportService implements JobHandler
{
    public function __construct(private Sql $db,private ImportSource $source,private ImportRepository $repository) {}
    public function type(): string { return 'commerce.import'; }

    public function start(TenantContext $actor,ConnectionContext $context,bool $full=false,bool $restart=false): string
    {
        if ($actor->merchantId!==$context->merchant->value) { throw new AccessDenied('forbidden'); }
        return $this->db->transaction(function() use ($actor,$context,$full,$restart): string {
            (new AccessPolicy($this->db))->require($actor,'operations.manage',$context->store->value);
            $connection=(new ConnectionGuard($this->db))->active($context,ProviderKind::Commerce);
            if ($connection['provider_key']!=='shopify') { throw new \InvalidArgumentException('Import source unavailable.'); }
            $merchant=Id::bytes($actor->merchantId);$store=Id::bytes($context->store->value);
            $this->db->run('INSERT INTO commerce_sync_heads(merchant_id,store_id,provider_key) VALUES(?,?,?) ON DUPLICATE KEY UPDATE provider_key=provider_key',[$merchant,$store,'shopify']);
            $head=$this->db->one('SELECT * FROM commerce_sync_heads WHERE merchant_id=? AND store_id=? AND provider_key=? FOR UPDATE',[$merchant,$store,'shopify']);
            if ($head===null) { throw new \LogicException('Missing import head.'); }
            if ($head['run_id']!==null) {
                $old=$this->db->one('SELECT status FROM commerce_sync_runs WHERE id=? FOR UPDATE',[(string)$head['run_id']]);
                if ($old!==null && $old['status']==='running') {
                    if (!$restart) { return bin2hex((string)$head['run_id']); }
                    $this->db->run("UPDATE commerce_sync_runs SET status='cancelled' WHERE id=?",[(string)$head['run_id']]);
                    $this->db->run('DELETE FROM commerce_sync_records WHERE run_id=?',[(string)$head['run_id']]);
                }
            }
            if ($this->db->one("SELECT 1 FROM commerce_privacy_blocks WHERE merchant_id=? AND store_id=? AND subject_type='shop'",[$merchant,$store])!==null) { throw new AccessDenied('shop_redacted'); }
            $id=Id::new();
            $this->db->run("INSERT INTO commerce_sync_runs(id,merchant_id,store_id,connection_id,provider_key,status,since_at) VALUES(?,?,?,?,?,'running',?)",[Id::bytes($id),$merchant,$store,Id::bytes($context->connection->value),'shopify',$full?null:$head['watermark']]);
            $this->db->run('UPDATE commerce_sync_heads SET run_id=? WHERE merchant_id=? AND store_id=? AND provider_key=?',[Id::bytes($id),$merchant,$store,'shopify']);
            foreach(['orders','products'] as $collection) { $this->task($actor->merchantId,$context->store->value,$id,$collection,''); }
            (new AuditLog($this->db))->append(new Scope($actor->merchantId,$context->store->value),Actor::user($actor->userId),AuditAction::ImportStarted,$id,new SafePayload(['connection_id'=>$context->connection->value]));
            return $id;
        });
    }

    public function handle(JobLease $job): void
    {
        $queue=new MySqlJobQueue($this->db);$queue->guard($job);$taskId=$job->payload->id('entity_id');
        $task=$this->db->one('SELECT * FROM commerce_sync_tasks WHERE merchant_id=? AND store_id=? AND id=?',[Id::bytes($job->scope->merchantId),Id::bytes((string)$job->scope->storeId),Id::bytes($taskId)]);
        if ($task===null) { throw new AccessDenied('import_scope'); }
        $run=$this->db->one('SELECT * FROM commerce_sync_runs WHERE id=?',[(string)$task['run_id']]);
        if ($run===null) { throw new \LogicException('Missing run.'); }
        if ($run['status']!=='running' || (bool)$task['done'] || (int)$task['page_number']!==($job->payload->values['version']??null)) { return; }
        $context=$this->context($run);(new ConnectionGuard($this->db))->active($context,ProviderKind::Commerce);
        // One bounded request per job. No business write is committed until lease and run are checked again.
        $page=$this->source->page($context,(string)$task['collection'],$task['parent_id']===''?null:(string)$task['parent_id'],$task['cursor_value']===null?null:(string)$task['cursor_value'],$run['since_at']===null?null:(string)$run['since_at']);
        $this->db->transaction(function() use ($job,$queue,$task,$context,$page): void {
            // Same head -> run lock order as start/restart; avoids a publication/restart deadlock.
            $this->db->one('SELECT run_id FROM commerce_sync_heads WHERE merchant_id=? AND store_id=? AND provider_key=? FOR UPDATE',[Id::bytes($context->merchant->value),Id::bytes($context->store->value),'shopify']);
            $run=$this->db->one('SELECT * FROM commerce_sync_runs WHERE id=? FOR UPDATE',[(string)$task['run_id']]);
            $queue->guard($job);
            if ($run===null || $run['status']!=='running') { return; }
            $current=$this->db->one('SELECT * FROM commerce_sync_tasks WHERE id=? FOR UPDATE',[(string)$task['id']]);
            if ($current===null || (bool)$current['done'] || $current['page_number']!==$task['page_number']) { return; }
            $this->db->one('SELECT id FROM provider_connections WHERE merchant_id=? AND id=? FOR SHARE',[(string)$run['merchant_id'],(string)$run['connection_id']]);
            (new ConnectionGuard($this->db))->active($context,ProviderKind::Commerce);
            foreach($page->records as $record) { $this->repository->stage($run,$record,(int)$task['page_number']>0); }
            foreach($page->children as $child) { $this->task($job->scope->merchantId,(string)$job->scope->storeId,bin2hex((string)$run['id']),$child['collection'],$child['parent']); }
            $this->db->run('UPDATE commerce_sync_tasks SET cursor_value=?,page_number=page_number+1,done=? WHERE id=?',[$page->nextCursor,$page->nextCursor===null?1:0,(string)$task['id']]);
            if ($page->nextCursor!==null) {
                if ((int)$task['page_number']>=10000) { throw new Conflict('pagination_limit'); }
                $this->enqueue($job->scope,bin2hex((string)$task['id']),(int)$task['page_number']+1);
            }
            if ($this->db->one('SELECT 1 FROM commerce_sync_tasks WHERE run_id=? AND done=FALSE LIMIT 1',[(string)$run['id']])===null) { $this->repository->publish($run); }
            // A large publication must still belong to this worker at commit time.
            $queue->guard($job);
        });
    }

    private function task(string $merchant,string $store,string $run,string $collection,string $parent): void
    {
        if (!in_array($collection,['orders','order','products','variants','inventory'],true)) { throw new \InvalidArgumentException('Invalid collection.'); }
        $existing=$this->db->one('SELECT id FROM commerce_sync_tasks WHERE run_id=? AND collection=? AND parent_id=?',[Id::bytes($run),$collection,$parent]);
        if ($existing!==null) { return; }$id=Id::new();
        $this->db->run('INSERT INTO commerce_sync_tasks(id,merchant_id,store_id,run_id,collection,parent_id) VALUES(?,?,?,?,?,?)',[Id::bytes($id),Id::bytes($merchant),Id::bytes($store),Id::bytes($run),$collection,$parent]);
        $this->enqueue(new Scope($merchant,$store),$id,0);
    }
    private function enqueue(Scope $scope,string $task,int $page): void
    {
        $id=(new MySqlJobQueue($this->db))->enqueue($scope,$this->type(),new SafePayload(['entity_id'=>$task,'version'=>$page]),new OperationKey('import:'.$task.':'.$page));
        $this->db->run('UPDATE commerce_sync_tasks SET job_id=? WHERE id=?',[Id::bytes($id),Id::bytes($task)]);
    }
    /** @param array<string,mixed> $run */
    private function context(array $run): ConnectionContext
    {
        return new ConnectionContext(new MerchantId(bin2hex((string)$run['merchant_id'])),new StoreId(bin2hex((string)$run['store_id'])),new ConnectionId(bin2hex((string)$run['connection_id'])),CorrelationId::new());
    }
}
