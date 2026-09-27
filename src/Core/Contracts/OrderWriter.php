<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface OrderWriter
{
    public function updateOrder(ConnectionContext $context, V\ExternalId $order, D\OrderChangeSet $changes, V\OperationKey $key): D\OrderSnapshot;
    public function addOrderMetadata(ConnectionContext $context, V\ExternalId $order, D\OrderMetadata $metadata, V\OperationKey $key): D\ActionResult;
}
