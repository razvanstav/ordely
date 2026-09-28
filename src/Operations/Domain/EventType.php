<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum EventType:string { case StoreCreated='STORE_CREATED';case StoreRenamed='STORE_RENAMED';case OrderImported='ORDER_IMPORTED'; }
