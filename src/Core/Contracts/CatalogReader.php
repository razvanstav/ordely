<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface CatalogReader
{
    /** @return Page<D\ProductSnapshot> */
    public function getProducts(ConnectionContext $context, V\PageRequest $page): Page;
    /** @return Page<D\ProductSnapshot> */
    public function searchProducts(ConnectionContext $context, string $query, V\PageRequest $page): Page;
    public function getProduct(ConnectionContext $context, V\ExternalId $id): D\ProductSnapshot;
    /** @return Page<D\VariantSnapshot> */
    public function getVariants(ConnectionContext $context, V\ExternalId $product, V\PageRequest $page): Page;
    /** @param list<V\ExternalId> $variants
     * @return list<D\InventorySnapshot> */
    public function getInventory(ConnectionContext $context, array $variants, V\ExternalId $location): array;
}
