<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface OrderReader
{
    public function getOrder(ConnectionContext $context, V\ExternalId $id): D\OrderSnapshot;
    /** @return Page<D\OrderSnapshot> */
    public function getOrders(ConnectionContext $context, V\PageRequest $page, ?\DateTimeImmutable $updatedSince = null): Page;
    public function getCustomer(ConnectionContext $context, V\ExternalId $id): D\CustomerSnapshot;
}
