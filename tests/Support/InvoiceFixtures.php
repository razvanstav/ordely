<?php
declare(strict_types=1);
namespace Ordely\Tests\Support;
use Ordely\Invoicing\Domain\DraftDocument;
use Ordely\Invoicing\Infrastructure\DraftCipher;

final class InvoiceFixtures
{
    /** @return array<string,mixed> */
    public static function data(): array
    {
        return ['reference'=>'LOCAL-TEST-09','customerName'=>'SYNTHETIC-PRIVATE-RECIPIENT','customerAddress'=>'SYNTHETIC-PRIVATE-ADDRESS','customerTaxId'=>'TEST-ONLY','currency'=>'RON',
            'lines'=>[['id'=>str_repeat('a',32),'description'=>'Synthetic item','quantity'=>2,'unitNet'=>'10.10','discountNet'=>'0.20','tax'=>'3.00']]];
    }
    public static function document(): DraftDocument { return DraftDocument::fromArray(self::data()); }
    public static function cipher(): DraftCipher { return new DraftCipher(IntegrationFixtures::cipher()); }
}
