<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
use Ordely\Core\Data as D;
use Ordely\Core\Value as V;
interface WebhookRegistrar
{
    /** @param list<D\WebhookSubscription> $subscriptions */
    public function registerWebhooks(ConnectionContext $context, array $subscriptions, V\OperationKey $key): D\RegistrationReport;
}
