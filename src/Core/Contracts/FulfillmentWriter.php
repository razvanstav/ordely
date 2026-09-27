<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface FulfillmentWriter
{
    public function createFulfillment(ConnectionContext $context, D\FulfillmentRequest $request, V\OperationKey $key): D\FulfillmentSnapshot;
    public function updateTracking(ConnectionContext $context, V\ExternalId $fulfillment, D\TrackingUpdate $tracking, V\OperationKey $key): D\FulfillmentSnapshot;
}
