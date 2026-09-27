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
    if($mode==='rotate'){
        $actor=new TenantContext($merchant,$argv[3]??'',$argv[4]??'',Role::Owner,true);
        $service=new \Ordely\Integrations\Infrastructure\Connections($db,\Ordely\Tests\Support\IntegrationFixtures::registry(),\Ordely\Tests\Support\IntegrationFixtures::cipher());
        try{$service->rotate($actor,$argv[5]??'',2);echo 'rotated';}catch(\Ordely\Operations\Domain\Conflict){echo 'conflict';}exit(0);
    }
    $store=$argv[3] ?? '';$connection=$argv[4] ?? '';
    $context=new ConnectionContext(new MerchantId($merchant),new StoreId($store),new ConnectionId($connection),CorrelationId::new());
    $result=(new ExternalOperations($db))->execute($context,'test.emit',new OperationKey('same-business-intent'),new SafePayload(['store_id'=>$store]),function(OperationKey $providerKey)use($db,$merchant,$mode):ExternalId{
        if($mode==='external-crash-before'){exit(24);}
        $db->run('INSERT INTO test_external_effects(merchant_id,provider_key) VALUES(?,?)',[Id::bytes($merchant),$providerKey->value]);
        if($mode==='external-crash'){exit(23);}
        usleep(200000);return new ExternalId('TEST-CONFIRMED');
    });
    echo json_encode(['id'=>$result->id,'state'=>$result->state->value],JSON_THROW_ON_ERROR);
}catch(Throwable $error){fwrite(STDERR,'Child failure: '.$error::class.PHP_EOL);exit(1);}
