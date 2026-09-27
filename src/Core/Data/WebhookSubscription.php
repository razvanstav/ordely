<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
final readonly class WebhookSubscription
{
    public function __construct(public string $topic, public string $callbackUrl)
    {
        if (!in_array($topic,['order.created','order.updated','product.updated','app.uninstalled'],true)
            || filter_var($callbackUrl,FILTER_VALIDATE_URL) === false || parse_url($callbackUrl,PHP_URL_SCHEME) !== 'https'
            || parse_url($callbackUrl,PHP_URL_USER) !== null || parse_url($callbackUrl,PHP_URL_PASS) !== null || strlen($callbackUrl) > 2048) { throw new \InvalidArgumentException('Invalid subscription.'); }
    }
}
