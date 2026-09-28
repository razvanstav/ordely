<?php
declare(strict_types=1);
// Shopify CLI supplies PORT and terminates TLS at its loopback proxy.
$port=getenv('PORT')?:'8080';
if(!ctype_digit($port)||(int)$port<1024||(int)$port>65535){throw new RuntimeException('Invalid development port.');}
putenv('ORDELY_TRUST_LOOPBACK_PROXY=1');
$process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t','public','public/index.php'],[STDIN,STDOUT,2=>['pipe','w']],$pipes,dirname(__DIR__));
if(!is_resource($process)){exit(1);}
// PHP's request logger can include Shopify ID tokens in query strings. Keep only safe application diagnostics.
while(($line=fgets($pipes[2]))!==false){
    if(preg_match('/Ordely (?:request|bootstrap) failure: [A-Za-z0-9_\\\\]+/',$line,$match)){fwrite(STDERR,$match[0].PHP_EOL);}
}
fclose($pipes[2]);exit(proc_close($process));
