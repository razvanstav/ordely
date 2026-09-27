<?php
declare(strict_types=1);
namespace Ordely\Core\Data;
final readonly class RegistrationReport
{
    /** @param list<WebhookSubscription> $subscriptions */
    public function __construct(public array $subscriptions) {}
}
