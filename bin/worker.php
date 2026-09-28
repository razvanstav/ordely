<?php
declare(strict_types=1);
use Ordely\Infrastructure\Database\{ConnectionFactory,DatabaseConfig,Sql};
use Ordely\Operations\Application\Worker;
use Ordely\Operations\Domain\EventType;
use Ordely\Operations\Infrastructure\{MySqlJobQueue,OutboxDispatcher,StoreEventHandler};

require dirname(__DIR__).'/config/bootstrap.php';
try{
    $limit=$argv[1] ?? '100';if(!ctype_digit($limit)||(int)$limit<1||(int)$limit>10000){throw new InvalidArgumentException('Invalid worker limit.');}
    $merchant=$argv[2] ?? null;
    $db=new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment()))->connect());
    (new \Ordely\Commerce\Infrastructure\ImportRetention($db))->clean();
    $dispatcher=new OutboxDispatcher($db,[EventType::StoreCreated->value=>['store.observe'],EventType::StoreRenamed->value=>['store.observe']]);
    $handlers=[new StoreEventHandler($db)];
    if (\Ordely\Infrastructure\Configuration\Environment::string('SHOPIFY_API_KEY',\Ordely\Infrastructure\Configuration\Environment::string('ORDELY_SHOPIFY_CLIENT_ID',''))!=='') {
        $handlers[]=\Ordely\Commerce\Infrastructure\CommerceFactory::importer($db);
    }
    $worker=new Worker(new MySqlJobQueue($db),$handlers);$processed=0;
    for($i=0;$i<(int)$limit;++$i){$dispatched=$dispatcher->dispatchOne($merchant);$result=$worker->once($merchant);if($result!=='idle'){++$processed;}if(!$dispatched&&$result==='idle'){break;}}
    echo 'Worker completed. Jobs handled: '.$processed.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Worker failed ('.$error::class.'). No raw payloads logged.'.PHP_EOL);exit(1);}
