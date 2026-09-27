<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
final readonly class OrderChangeSet
{
    // First portable edit: an order note. New business edits get explicit fields, not provider JSON.
    public function __construct(public string $note)
    {
        if (mb_strlen($note) > 2000 || str_contains($note,"\0")) { throw new \InvalidArgumentException('Invalid order note.'); }
    }
}
