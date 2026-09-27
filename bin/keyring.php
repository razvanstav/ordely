<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use Ordely\Integrations\Infrastructure\KeyRing;

// Operator CLI: create a NEW keyring, optionally retaining every key from the old one.
try{
    $name=$argv[1]??'keyring';$old=$argv[2]??null;
    foreach([$name,...($old===null?[]:[$old])] as $part){if(!preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$part)){throw new RuntimeException('Invalid keyring name.');}}
    $directory=dirname(__DIR__).'/var/keys';
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory)){throw new RuntimeException('Key directory unavailable.');}
    $keys=[];
    if($old!==null){
        $file=$directory.'/'.$old.'.json';if(!is_file($file)){throw new RuntimeException('Old keyring missing.');}
        $json=file_get_contents($file);if($json===false){throw new RuntimeException('Old keyring unavailable.');}KeyRing::fromJson($json);
        $data=json_decode($json,true,flags:JSON_THROW_ON_ERROR);$keys=$data['keys'];
    }
    $active='key-'.bin2hex(random_bytes(8));$keys[$active]=base64_encode(random_bytes(32));
    $json=json_encode(['active'=>$active,'keys'=>$keys],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);KeyRing::fromJson($json);
    $file=$directory.'/'.$name.'.json';$previous=umask(0077);
    try{$handle=@fopen($file,'x');}finally{umask($previous);}
    if($handle===false){throw new RuntimeException('Target exists or is not writable.');}
    try{if(fwrite($handle,$json."\n")!==strlen($json)+1||!fflush($handle)){throw new RuntimeException('Keyring write failed.');}}finally{fclose($handle);}
    echo "Created var/keys/".$name.".json; key material was not printed.\n";
}catch(Throwable $error){fwrite(STDERR,'Keyring command failed: '.$error::class.".\n");exit(1);}
