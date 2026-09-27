<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class ActionResult
{
    public function __construct(public ExternalId $reference, public bool $accepted) {}
}
