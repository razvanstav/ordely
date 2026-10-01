<?php
declare(strict_types=1);
use Ordely\Core\Contracts\ConnectionContext;
use Ordely\Core\Value\{MerchantId,StoreId,ConnectionId,CorrelationId,ExternalId,OperationKey};
use Ordely\Identity\Domain\{Role,TenantContext};
use Ordely\Identity\Infrastructure\StoreRepository;
use Ordely\Infrastructure\Database\{ConnectionFactory,DatabaseConfig,Sql};
use Ordely\Operations\Domain\SafePayload;
use Ordely\Operations\Infrastructure\{ExternalOperations,IdempotencyGate,MySqlJobQueue};
use Ordely\Shared\Id;

require dirname(__DIR__,2).'/config/bootstrap.php';
try{
    $db=new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(true)))->connect());
    echo "ready\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='go'){exit(10);}
    $mode=$argv[1] ?? '';$merchant=$argv[2] ?? '';
    if($mode==='claim'){
        $queue=new MySqlJobQueue($db);$job=$queue->claim($merchant);
        if($job!==null){usleep(200000);$queue->acknowledge($job);}
        echo json_encode(['id'=>$job?->id],JSON_THROW_ON_ERROR);exit(0);
    }
    if($mode==='idempotency'){
        $actor=new TenantContext($merchant,$argv[3] ?? '',$argv[4] ?? '',Role::Owner,true);
        $result=(new IdempotencyGate($db))->run($actor,null,'stores.create','stores.manage',new OperationKey('same-http-request'),['name'=>'Test concurrent','platform'=>'manual'],function()use($db,$actor):SafePayload{
            usleep(200000);return new SafePayload(['store_id'=>(new StoreRepository($db))->create($actor,'Test concurrent','manual')]);
        });
        echo json_encode(['id'=>$result->id('store_id')],JSON_THROW_ON_ERROR);exit(0);
    }
    if(in_array($mode,['invoice-issue-prepare','invoice-issue-execute','invoice-issue-crash','invoice-issue-cancel','invoice-issue-race-execute'],true)){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);$store=$argv[5]??'';$id=$argv[6]??'';
        $service=\Ordely\Tests\Support\IssueFixtures::service($db,\Ordely\Tests\Support\IntegrationFixtures::cipher());
        if($mode==='invoice-issue-prepare'){echo json_encode($service->prepare($actor,$store,$id,2),JSON_THROW_ON_ERROR);exit(0);}
        if($mode==='invoice-issue-cancel'){try{echo json_encode($service->cancel($actor,$store,$id,1),JSON_THROW_ON_ERROR);}catch(\Ordely\Operations\Domain\Conflict){echo '{"status":"conflict"}';}exit(0);}
        if($mode==='invoice-issue-race-execute'){
            try{$result=$service->execute($actor,$store,$id,static function(ConnectionContext $context,\Ordely\Core\Data\InvoiceDraft $draft,OperationKey $key):\Ordely\Core\Data\InvoiceSnapshot{usleep(200000);return \Ordely\Tests\Support\IssueFixtures::issued($draft);});echo json_encode(['status'=>$result->state->value],JSON_THROW_ON_ERROR);}catch(\Ordely\Operations\Domain\Conflict){echo '{"status":"cancelled"}';}exit(0);
        }
        $result=$service->execute($actor,$store,$id,static function(ConnectionContext $context,\Ordely\Core\Data\InvoiceDraft $draft,OperationKey $key)use($mode):\Ordely\Core\Data\InvoiceSnapshot{if($mode==='invoice-issue-crash'){exit(23);}usleep(200000);return \Ordely\Tests\Support\IssueFixtures::issued($draft);});
        echo json_encode(['id'=>$result->id,'status'=>$result->state->value],JSON_THROW_ON_ERROR);exit(0);
    }
    if($mode==='rotate'){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);
        $service=new \Ordely\Integrations\Infrastructure\Connections($db,\Ordely\Tests\Support\IntegrationFixtures::registry(),\Ordely\Tests\Support\IntegrationFixtures::cipher());
        try{$service->rotate($actor,$argv[5]??'',2);echo 'rotated';}catch(\Ordely\Operations\Domain\Conflict){echo 'conflict';}exit(0);
    }
    if($mode==='invoice-create'||$mode==='invoice-edit'){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);$store=$argv[5]??'';$id=$argv[6]??'';
        $repo=new \Ordely\Invoicing\Infrastructure\DraftRepository($db,\Ordely\Tests\Support\InvoiceFixtures::cipher());$document=\Ordely\Tests\Support\InvoiceFixtures::document();
        if($mode==='invoice-create'){echo $repo->create($actor,$store,$id,$document);}
        else{try{$repo->replace($actor,$store,$id,1,$document);echo 'updated';}catch(\Ordely\Operations\Domain\Conflict){echo 'conflict';}}exit(0);
    }
    if(in_array($mode,['invoice-order-save','invoice-order-complete'],true)){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);$cipher=\Ordely\Tests\Support\IntegrationFixtures::cipher();
        $source=new \Ordely\Invoicing\Infrastructure\OrderPreparations($db,new \Ordely\Commerce\Infrastructure\OrderCipher($cipher),new \Ordely\Invoicing\Infrastructure\InvoiceProfiles($db,new \Ordely\Integrations\Application\ProviderRegistry(),$cipher));
        $repo=new \Ordely\Invoicing\Infrastructure\OrderDrafts($db,$source,new \Ordely\Invoicing\Infrastructure\PreparationCipher($cipher));
        try{echo $mode==='invoice-order-complete'?$repo->complete($actor,$argv[5]??'',$argv[6]??'',(int)($argv[7]??0),\Ordely\Tests\Support\PreparationFixtures::fiscalDetails()):$repo->save($actor,$argv[5]??'',$argv[6]??'',(int)($argv[7]??0),(int)($argv[8]??3),0);}catch(\Ordely\Operations\Domain\Conflict){echo 'conflict';}exit(0);
    }
    if($mode==='invoice-profile'){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);
        $context=new ConnectionContext(new MerchantId($merchant),new StoreId($argv[5]??''),new ConnectionId($argv[6]??''),CorrelationId::new());
        $profiles=new \Ordely\Invoicing\Infrastructure\InvoiceProfiles($db,\Ordely\Tests\Support\OblioFixtures::profileRegistry(),\Ordely\Tests\Support\IntegrationFixtures::cipher());
        try{echo $profiles->save($actor,$context,2,(int)($argv[7]??0),'TEST001',$argv[8]??'TEST');}catch(\Ordely\Operations\Domain\Conflict){echo 'conflict';}exit(0);
    }
    $store=$argv[3] ?? '';$connection=$argv[4] ?? '';
    $context=new ConnectionContext(new MerchantId($merchant),new StoreId($store),new ConnectionId($connection),CorrelationId::new());
    if($mode==='shopify-refresh'){
        $gateway=new \Ordely\Tests\Support\FakeShopifyGateway();
        $service=new \Ordely\Adapters\Shopify\Installations($db,\Ordely\Tests\Support\ShopifyFixtures::config(),\Ordely\Tests\Support\IntegrationFixtures::cipher(),$gateway);
        $service->withAccess($context,static function():null{usleep(150000);return null;});
        echo json_encode(['refreshes'=>$gateway->refreshes],JSON_THROW_ON_ERROR);exit(0);
    }
    if($mode==='commerce-start'||$mode==='commerce-work'){
        $cipher=new \Ordely\Commerce\Infrastructure\OrderCipher(\Ordely\Tests\Support\IntegrationFixtures::cipher());
        $source=new \Ordely\Tests\Support\ImportFixtures();$source->beforePage=static function():void{usleep(50000);};
        $service=new \Ordely\Commerce\Application\ImportService($db,$source,new \Ordely\Commerce\Infrastructure\ImportRepository($db,$cipher));
        if($mode==='commerce-start'){
            $actor=new TenantContext($merchant,$argv[5]??'',$argv[6]??'',Role::Owner,true);
            echo json_encode(['id'=>$service->start($actor,$context)],JSON_THROW_ON_ERROR);exit(0);
        }
        $worker=new \Ordely\Operations\Application\Worker(new MySqlJobQueue($db),[$service]);$statuses=[];
        for($i=0;$i<25;++$i){$status=$worker->once($merchant);$statuses[]=$status;if($status==='idle'){break;}}
        echo json_encode($statuses,JSON_THROW_ON_ERROR);exit(0);
    }
    $result=(new ExternalOperations($db))->execute($context,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$store]),function(OperationKey $providerKey)use($db,$merchant,$mode):ExternalId{
        if($mode==='external-crash-before'){exit(24);}
        $db->run('INSERT INTO test_external_effects(merchant_id,provider_key) VALUES(?,?)',[Id::bytes($merchant),$providerKey->value]);
        if($mode==='external-crash'){exit(23);}
        usleep(200000);return new ExternalId('TEST-CONFIRMED');
    });
    echo json_encode(['id'=>$result->id,'state'=>$result->state->value],JSON_THROW_ON_ERROR);
}catch(Throwable $error){fwrite(STDERR,'Child failure: '.$error::class.PHP_EOL);exit(1);}
