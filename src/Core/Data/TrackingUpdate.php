<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
use Ordely\Core\Value\ExternalId;
final readonly class TrackingUpdate
{
    public function __construct(public ExternalId $number, public string $carrier, public ?string $url = null)
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $carrier) || ($url !== null && (strlen($url) > 2048 || filter_var($url,FILTER_VALIDATE_URL) === false || parse_url($url,PHP_URL_SCHEME) !== 'https'))) { throw new \InvalidArgumentException('Invalid tracking update.'); }
    }
}
