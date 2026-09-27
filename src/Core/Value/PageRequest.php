<?php
declare(strict_types=1);
namespace Ordely\Core\Value;
final readonly class PageRequest
{
    public function __construct(public int $limit = 50, public ?string $cursor = null)
    {
        if ($limit < 1 || $limit > 100 || ($cursor !== null && (strlen($cursor) > 2048 || $cursor === '' || preg_match('/[\\x00-\\x1f\\x7f]/', $cursor)))) { throw new \InvalidArgumentException('Invalid page request.'); }
    }
}
