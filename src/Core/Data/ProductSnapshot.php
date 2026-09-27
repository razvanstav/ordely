<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class ProductSnapshot
{
    public function __construct(public ExternalId $id, public string $title, public bool $active = true)
    {
        if (trim($title) === '' || mb_strlen($title) > 255) { throw new \InvalidArgumentException('Invalid product.'); }
    }
}
