<?php
declare(strict_types=1);
namespace Ordely\Core\Contracts;
enum Capability: string
{
    case ReadOrders='read_orders'; case ReadCustomer='read_customer'; case ReadCatalog='read_catalog'; case ReadInventory='read_inventory';
    case Fulfillment='fulfillment'; case TrackingWrite='tracking_write'; case EditOrder='edit_order'; case Metadata='metadata'; case NativeReturns='native_returns'; case Webhooks='webhooks';
    case Shipment='shipment'; case ReturnShipment='return_shipment'; case CancelShipment='cancel_shipment'; case Label='label'; case TrackingRead='tracking_read';
    case Cod='cod'; case Pickup='pickup'; case PickupPoints='pickup_points'; case ParcelExchange='parcel_exchange'; case MultiplePackages='multiple_packages'; case Rates='rates';
    case InvoiceConfiguration='invoice_configuration';
    case Invoice='invoice'; case CancelInvoice='cancel_invoice'; case CreditNote='credit_note'; case Pdf='pdf'; case SendInvoice='send_invoice';
    case NativeIdempotency='native_idempotency'; case LookupOperation='lookup_operation';
}
