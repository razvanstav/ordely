<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use Ordely\Infrastructure\Database\{ConnectionFactory,DatabaseConfig,Sql};

try{
    $db=new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(in_array('--test',$argv,true))))->connect());
    $rows=$db->run("SELECT JSON_UNQUOTE(JSON_EXTRACT(credentials_envelope,'$.key')) key_id,COUNT(*) connections,SUM(status='revoked') revoked FROM provider_connections GROUP BY key_id ORDER BY key_id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['keyUsage'=>$rows],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Key usage unavailable: '.$error::class.PHP_EOL);exit(1);}
