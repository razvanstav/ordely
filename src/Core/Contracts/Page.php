<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
/** @template T */
final readonly class Page
{
    /** @var list<T> */
    public array $items;
    /** @param array<array-key,T> $items */
    public function __construct(array $items, public ?string $nextCursor = null)
    {
        if (!array_is_list($items) || count($items) > 100) { throw new \InvalidArgumentException('Invalid result page.'); }
        $this->items = $items;
    }
}
