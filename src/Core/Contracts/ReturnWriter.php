<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface ReturnWriter
{
    public function createReturn(ConnectionContext $context, D\ReturnRequest $request, V\OperationKey $key): D\ReturnSnapshot;
    public function updateReturn(ConnectionContext $context, V\ExternalId $return, D\ReturnState $state, V\OperationKey $key): D\ReturnSnapshot;
}
