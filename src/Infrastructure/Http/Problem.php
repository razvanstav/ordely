<?php
declare(strict_types=1);
namespace Ordely\Infrastructure\Http;

final class Problem extends \RuntimeException
{
    public function __construct(public readonly int $status, string $code) { parent::__construct($code); }
}
