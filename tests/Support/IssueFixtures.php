<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Commerce\Infrastructure\OrderCipher;
use Ordely\Identity\Domain\TenantContext;
use Ordely\Infrastructure\Database\Sql;
use Ordely\Integrations\Application\ProviderRegistry;
use Ordely\Integrations\Domain\Secrets;
use Ordely\Integrations\Infrastructure\{Connections,SecretCipher};
use Ordely\Invoicing\Infrastructure\{InvoiceProfiles,IssueCipher,IssueIntents,OrderPreparations,OrderDrafts,PreparationCipher};
use Ordely\Shared\Id;

final class IssueFixtures
{
    public static function drafts(Sql $db,SecretCipher $cipher): OrderDrafts {return new OrderDrafts($db,new OrderPreparations($db,new OrderCipher($cipher),new InvoiceProfiles($db,new ProviderRegistry(),$cipher)),new PreparationCipher($cipher));}
    public static function service(Sql $db,SecretCipher $cipher): IssueIntents {return new IssueIntents($db,self::drafts($db,$cipher),new IssueCipher($cipher));}
    /** @return array{order:string,connection:string} */
    public static function ready(Sql $db,TenantContext $actor,string $store,SecretCipher $cipher): array
    {
        $order=PreparationFixtures::persist($db,$actor,$store,$cipher);
        $run=$db->one('SELECT connection_id FROM commerce_sync_runs WHERE merchant_id=? AND store_id=?',[Id::bytes($actor->merchantId),Id::bytes($store)]);
        if($run===null||!is_string($run['connection_id']??null)){throw new \LogicException('Synthetic issue fixture missing.');}
        $connection=bin2hex($run['connection_id']);
        (new Connections($db,IntegrationFixtures::registry(),$cipher))->bind($actor,$connection,1,$store);
        $envelope=$cipher->encrypt($actor->merchantId,$store,'invoice-profile:'.$store.':'.$connection.':2:1',new Secrets(['companyId'=>'TEST009','companyName'=>'Synthetic seller','series'=>'TEST']));
        $db->run('INSERT INTO invoice_profiles(merchant_id,store_id,connection_id,connection_version,version,profile_envelope) VALUES(?,?,?,2,1,?)',[Id::bytes($actor->merchantId),Id::bytes($store),Id::bytes($connection),$envelope]);
        $drafts=self::drafts($db,$cipher);$drafts->save($actor,$store,$order,0,3,1);$drafts->complete($actor,$store,$order,1,PreparationFixtures::fiscalDetails());
        return ['order'=>$order,'connection'=>$connection];
    }
}
