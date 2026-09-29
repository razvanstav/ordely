<?php
declare(strict_types=1);
namespace Ordely\Core\Data;

/** Read-only discovery. This is neither a saved fiscal profile nor authority to issue documents. */
final readonly class InvoiceConfiguration
{
    /** @param list<array{id:string,name:string}> $companies
     * @param list<array{name:string,default:bool}> $series
     * @param list<array{name:string,percent:string,default:bool}> $taxRates */
    public function __construct(public array $companies, public ?string $companyId = null, public array $series = [], public array $taxRates = []) {}
}
