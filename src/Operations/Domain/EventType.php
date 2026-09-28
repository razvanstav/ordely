<?php
declare(strict_types=1);
namespace Ordely\Operations\Domain;
enum EventType:string { case StoreCreated='STORE_CREATED';case StoreRenamed='STORE_RENAMED';case OrderImported='ORDER_IMPORTED';case InvoiceDraftCreated='INVOICE_DRAFT_CREATED';case InvoiceDraftUpdated='INVOICE_DRAFT_UPDATED';case InvoiceDraftArchived='INVOICE_DRAFT_ARCHIVED'; }
