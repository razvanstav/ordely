<?php
declare(strict_types=1);
namespace Ordely\Core\Data;

final readonly class ImportPage
{
    /** @param array<array-key,array<string,mixed>> $records
     * @param list<array{collection:string,parent:string}> $children */
    public function __construct(public array $records, public ?string $nextCursor = null, public array $children = [])
    {
        if (!array_is_list($records) || count($records) > 100 || ($nextCursor !== null && (strlen($nextCursor) > 2048 || $nextCursor === ''))) {
            throw new \InvalidArgumentException('Invalid import page.');
        }
    }
}
