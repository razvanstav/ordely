<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
interface CommerceConnector extends CapabilitiesProvider, OrderReader, CatalogReader, FulfillmentWriter, OrderWriter, ReturnWriter, WebhookRegistrar {}
