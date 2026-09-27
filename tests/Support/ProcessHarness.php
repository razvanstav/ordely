<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;

final class ProcessHarness
{
    /** @param list<list<string>> $commands
     * @return list<array{exit:int,output:string,error:string}> */
    public static function together(array $commands): array
    {
        $children=[];$results=[];
        try{
            foreach($commands as $arguments){
                $process=proc_open([PHP_BINARY,dirname(__DIR__).'/Support/operations-process.php',...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__,2),options:['bypass_shell'=>true]);
                if(!is_resource($process)){throw new \RuntimeException('Could not start test process.');}
                stream_set_timeout($pipes[1],15);stream_set_timeout($pipes[2],15);
                $children[]=['process'=>$process,'pipes'=>$pipes];
            }
            foreach($children as $child){if(trim((string)fgets($child['pipes'][1]))!=='ready'){throw new \RuntimeException('Child did not reach barrier.');}}
            foreach($children as $child){fwrite($child['pipes'][0],"go\n");fclose($child['pipes'][0]);}
            foreach($children as $child){
                $out=stream_get_contents($child['pipes'][1]);$error=stream_get_contents($child['pipes'][2]);
                fclose($child['pipes'][1]);fclose($child['pipes'][2]);
                $results[]=['exit'=>proc_close($child['process']),'output'=>$out===false?'':$out,'error'=>$error===false?'':$error];
            }
            return $results;
        }finally{
            foreach($children as $child){foreach($child['pipes'] as $pipe){if(is_resource($pipe)){fclose($pipe);}}if(is_resource($child['process'])){proc_terminate($child['process']);proc_close($child['process']);}}
        }
    }
}
