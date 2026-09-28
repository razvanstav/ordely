<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use Ordely\Infrastructure\Database\{ConnectionFactory,DatabaseConfig,Sql};

try{
    $db=new Sql((new ConnectionFactory(DatabaseConfig::fromEnvironment(in_array('--test',$argv,true))))->connect());
    $rows=$db->run("SELECT JSON_UNQUOTE(JSON_EXTRACT(credentials_envelope,'$.key')) key_id,COUNT(*) connections,SUM(status='revoked') revoked FROM provider_connections GROUP BY key_id ORDER BY key_id")->fetchAll(PDO::FETCH_ASSOC);
    $webhooks=$db->run("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload_envelope,'$.key')) key_id,COUNT(*) webhooks FROM shopify_webhook_events GROUP BY key_id ORDER BY key_id")->fetchAll(PDO::FETCH_ASSOC);
    $orders=$db->run("SELECT parts.key_id,COUNT(*) chunks FROM (SELECT document FROM commerce_records WHERE kind='order' UNION ALL SELECT document FROM commerce_sync_records WHERE kind='order') documents JOIN JSON_TABLE(documents.document,'$.chunks[*]' COLUMNS(key_id VARCHAR(64) PATH '$.key')) parts GROUP BY parts.key_id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['keyUsage'=>$rows,'webhookKeyUsage'=>$webhooks,'orderKeyUsage'=>$orders],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Key usage unavailable: '.$error::class.PHP_EOL);exit(1);}
