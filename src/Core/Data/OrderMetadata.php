<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
final readonly class OrderMetadata
{
    /** @param array<string,string> $values */
    public function __construct(public array $values)
    {
        foreach ($values as $key => $value) {
            if (!in_array($key,['ordely.return_id','ordely.exchange_id','ordely.operation_id'],true) || !preg_match('/^[a-f0-9]{32}$/D',$value)) { throw new \InvalidArgumentException('Only Ordely entity references are allowed.'); }
        }
    }
}
