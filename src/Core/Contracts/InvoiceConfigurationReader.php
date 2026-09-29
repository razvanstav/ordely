<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data\InvoiceConfiguration;
use Ordely\Core\Value\ExternalId;

interface InvoiceConfigurationReader
{
    public function readConfiguration(ConnectionContext $context, ?ExternalId $company = null): InvoiceConfiguration;
}
