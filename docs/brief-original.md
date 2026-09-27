# ORDELY – BRIEF PENTRU ARHITECTURĂ

## 1. Obiectiv

Vreau să construiesc Ordely, o platformă SaaS modulară pentru operațiunile magazinelor online.

Ordely trebuie să conecteze într-un singur sistem:

- comenzile;
- facturarea;
- curierii;
- AWB-urile;
- tracking-ul;
- retururile;
- schimburile;
- rambursările către clienți;
- retrimiterile;
- automatizările operaționale.

Prima integrare va fi Shopify.

Ulterior trebuie să putem adăuga foarte ușor:

- WooCommerce;
- OpenCart;
- PrestaShop;
- Magento;
- Cartive;
- alte CMS-uri/platforme e-commerce.

Cerința arhitecturală principală este:

ONE CORE + MULTIPLE CONNECTORS.

Business logic-ul Ordely NU trebuie să depindă de Shopify.

---

# 2. Stack tehnic dorit

Backend:

PHP  
MySQL

Frontend:

HTML  
CSS  
JavaScript Vanilla

Nu vreau React/Vue/Angular pentru frontend.

API-urile interne pot fi REST/JSON.

Pentru task-uri asincrone trebuie proiectat un sistem de jobs/workers compatibil cu PHP.

Pentru V1 se poate folosi inclusiv o coadă bazată pe MySQL + cron/workers, dar arhitectura trebuie să permită ulterior Redis sau alt queue system fără rescrierea business logic-ului.

---

# 3. Arhitectura generală

Conceptual:

```text
                         CMS / COMMERCE

       Shopify   WooCommerce   OpenCart   PrestaShop   Cartive
          │           │            │           │          │
          └───────────┴────────────┴───────────┴──────────┘
                              │
                    COMMERCE CONNECTORS
                              │
                              ▼
                         ORDELY CORE
                              │
          ┌───────────────────┼──────────────────┐
          │                   │                  │
       ORDERS              RETURNS           EXCHANGES
          │                   │                  │
          ├───────────┬───────┴───────┬──────────┤
          │           │               │          │
      SHIPPING     TRACKING        REFUNDS    INVOICING
          │                                      │
          ▼                                      ▼
   CARRIER PROVIDERS                     INVOICE PROVIDERS
          │                                      │
   FAN / Sameday etc.                 Oblio / SmartBill / FGO
```

Core-ul nu trebuie să știe dacă o comandă provine din Shopify sau WooCommerce.

---

# 4. Tipurile principale de extensii

Ordely trebuie să aibă trei familii de integrări.

## Commerce Connectors

```text
ShopifyConnector
WooCommerceConnector
OpenCartConnector
PrestaShopConnector
CartiveConnector
```

## Carrier Providers

```text
SamedayProvider
FanCourierProvider
DPDProvider
CargusProvider
GLSProvider
```

## Invoice Providers

```text
OblioProvider
SmartBillProvider
FGOProvider
```

Adăugarea unui CMS, curier sau operator de facturare nou nu trebuie să necesite modificarea Core-ului.

---

# 5. Interfața Commerce Connector

Toate CMS-urile trebuie să implementeze o interfață comună.

Conceptual:

```text
getOrder()
getOrders()
getCustomer()

getProducts()
searchProducts()

getProduct()
getVariants()
getInventory()

createFulfillment()
updateTracking()

updateOrder()
addOrderMetadata()

createReturn()
updateReturn()

registerWebhooks()
```

Datele specifice CMS-ului trebuie transformate într-un model intern Ordely.

Exemplu:

```text
Shopify Order
      ↓
ShopifyConnector
      ↓
UnifiedOrder
```

sau:

```text
WooCommerce Order
      ↓
WooCommerceConnector
      ↓
UnifiedOrder
```

Restul aplicației lucrează exclusiv cu obiectele interne Ordely.

---

# 6. Regula de dependență

Vreau o regulă strictă:

Core-ul Ordely NU are voie să importe sau să depindă direct de:

```text
Shopify
WooCommerce
OpenCart
PrestaShop
```

Connectorii implementează interfețele definite de Core.

Dependența este:

```text
Connector
   ↓
Core Interface
```

NU:

```text
Core
   ↓
Shopify
```

---

# 7. Interfața Carrier Provider

Conceptual:

```text
createShipment()

createReturnShipment()

cancelShipment()

getShipment()

getLabel()

getTracking()

createPickup()

cancelPickup()

getPickupPoints()

calculateRate()

getServices()
```

Providerul trebuie să declare și capabilities.

Exemplu:

```text
supportsCOD

supportsPickup

supportsReturnShipment

supportsParcelExchange

supportsLocker

supportsPickupPoints

supportsMultiplePackages
```

UI-ul Ordely trebuie să afișeze doar funcțiile permise de curierul respectiv.

De exemplu:

Dacă FAN permite „colet la schimb”, funcția apare.

Dacă un alt curier nu permite, funcția nu apare sau Ordely folosește workflow-ul alternativ.

---

# 8. Interfața Invoice Provider

Conceptual:

```text
createInvoice()

cancelInvoice()

createCreditNote()

getInvoice()

getPdf()

sendInvoice()
```

Primele integrări:

```text
Oblio
```

Ulterior:

```text
SmartBill
FGO
```

Ordely NU trebuie să devină program contabil.

Ordely folosește operatorii externi pentru emiterea documentelor.

---

# 9. Flux comandă normală

Intră o comandă în Shopify.

Ordely o sincronizează.

Operatorul trebuie să poată selecta:

```text
Facturează
```

sau:

```text
Generează AWB
```

sau, ideal:

```text
Facturează + Generează AWB
```

Flow:

```text
Order
  ↓
Invoice Provider
  ↓
Invoice
  ↓
Carrier Provider
  ↓
AWB
  ↓
Tracking
  ↓
Update Commerce Platform
```

Factura trebuie emisă automat folosind datele comenzii.

---

# 10. Customer Return Portal / Widget

Ordely trebuie să aibă un portal de retur construit O SINGURĂ DATĂ.

Portalul este hostat de Ordely.

Exemplu intern:

```text
returns.ordely.ro/w/{store-token}
```

Nu vreau câte un portal separat pentru fiecare CMS.

---

# 11. Integrarea widget-ului în magazin

Fiecare Commerce Connector trebuie doar să poată instala / afișa launcher-ul Ordely.

Concept:

```html
<script
    src="https://cdn.ordely.ro/widget.js"
    data-store="STORE_PUBLIC_TOKEN">
</script>
```

Acest script poate genera:

```text
[ Retur / Schimb ]
```

La click se deschide un modal/full-screen overlay.

În interior este încărcat portalul Ordely.

Concept:

```text
Merchant Website
      ↓
Ordely widget.js
      ↓
Full-screen overlay
      ↓
Ordely Returns Portal
```

Preferabil, conținutul portalului poate fi izolat într-un iframe pentru a evita conflictele între CSS-ul magazinului și CSS-ul Ordely.

Portalul nu trebuie să depindă de cookies third-party pentru funcționarea de bază.

Identificarea magazinului trebuie făcută prin token/session sigură.

---

# 12. Variante de integrare per CMS

Shopify:

Theme App Extension care încarcă launcher-ul/widget-ul Ordely.

WooCommerce:

plugin care introduce widget-ul sau shortcode/block.

OpenCart:

extension care introduce widget-ul.

PrestaShop:

module care introduce widget-ul.

Important:

Toate acestea trebuie să încarce ACELAȘI portal Ordely.

Nu construim UI-ul returului din nou pentru fiecare CMS.

---

# 13. White-label widget

Fiecare comerciant poate configura:

```text
Logo

Gradient start
Gradient end

Primary color

Button color

Text color

Border radius

Titlu portal

Texte

Politica retur
```

Exemplu:

```text
Logo MEAI

Gradient:
#071A33 → #214A7A
```

Clientul trebuie să simtă că portalul aparține magazinului.

---

# 14. Identificarea clientului

Clientul intră în portal și introduce:

```text
ID comandă

Telefon / email
```

Ordely verifică prin Commerce Connector dacă datele coincid.

Arhitectura trebuie să permită și OTP ulterior sau configurabil pentru securitate.

După validare, Ordely afișează produsele eligibile din comandă.

---

# 15. Opțiunile clientului

Pentru fiecare produs eligibil:

```text
Retur bani

Schimb mărime

Schimb produs
```

Pentru schimb mărime:

Ordely cere CMS-ului:

```text
getVariants()
getInventory()
```

și afișează doar variantele disponibile.

Exemplu:

```text
Polo Navy

Mărime cumpărată:
M

Alege:

S     disponibil
L     disponibil
XL    indisponibil
```

Varianta fără stoc nu trebuie selectabilă.

---

# 16. Retur cu rambursarea banilor

Clientul selectează:

```text
Retur bani
```

Selectează motivul.

Exemple:

```text
Mărime nepotrivită
Nu corespunde așteptărilor
Produs defect
Alt motiv
```

Dacă rambursarea se face prin transfer bancar, clientul trebuie obligatoriu să introducă:

```text
Titular cont
IBAN
```

IBAN-ul trebuie validat.

În baza de date trebuie stocat securizat/criptat.

În UI trebuie afișat mascat unde nu este necesară valoarea completă:

```text
RO••••••••••1234
```

---

# 17. Refund Queue

Retururile cu bani trebuie să intre într-o coadă operațională.

Exemplu dashboard:

```text
RETURURI

12 Așteaptă coletul

4 Retururi primite

3 De rambursat

1 Rambursat astăzi
```

Refund status:

```text
NOT_REQUIRED

PENDING

READY_FOR_REFUND

REFUNDED

FAILED
```

---

# 18. Ridicare retur

Clientul finalizează cererea.

Ordely poate crea:

```text
ReturnCase
```

și poate genera automat:

```text
AWB ridicare
```

Flow:

```text
Client
  ↓
Return Request
  ↓
ReturnCase
  ↓
CarrierProvider.createReturnShipment()
  ↓
AWB Retur
  ↓
Pickup
  ↓
Tracking
  ↓
Magazin
```

Returul trebuie să rămână în dashboard până la finalizare.

---

# 19. Return statuses

Exemplu state machine:

```text
REQUESTED

APPROVED

AWB_CREATED

PICKUP_SCHEDULED

IN_TRANSIT

RECEIVED

INSPECTED

RESOLVED

CANCELLED
```

Trebuie proiectată state machine clară.

---

# 20. Exchange Case

Schimbul trebuie tratat ca un caz propriu.

Exemplu:

```text
Comanda #1254
      │
      └── Exchange EX-184
             │
             ├── Polo M returnat
             ├── Polo L solicitat
             │
             ├── AWB inbound
             │
             └── Outbound Shipment
```

Inbound și outbound trebuie să rămână legate de același ExchangeCase.

---

# 21. Primirea returului

Când produsul ajunge:

```text
[ Marchează retur primit ]
```

Exchange-ul intră în starea:

```text
READY_FOR_OUTBOUND
```

Operatorul poate genera expedierea nouă.

---

# 22. Expedierea produsului după retur/schimb

Aici există una dintre regulile importante ale produsului.

Operatorului NU îi cerem:

```text
Ce ramburs vrei?
```

Îl întrebăm:

```text
Ce facturezi?

○ Nimic

☐ Produse

☐ Transport
```

Operatorul spune CE vinde/încasează.

Ordely calculează automat suma.

---

# 23. Opțiunea NIMIC

Dacă:

```text
Nimic
```

atunci:

```text
Invoice = NONE

COD = 0

Shipment = YES
```

Nu se emite factură nouă.

Aceasta este regula V1.

Arhitectura poate permite ulterior introducerea unui FiscalPolicy/FiscalEngine fără rescrierea ExchangeService.

---

# 24. Opțiunea TRANSPORT

Dacă operatorul bifează:

```text
Transport
```

Ordely ia automat valoarea transportului configurată.

Exemplu:

```text
Taxă transport schimb:
19 lei
```

Rezultat:

```text
Factura:

Transport
1 × 19 lei

Total:
19 lei
```

Ordely setează:

```text
COD = 19 lei
```

Nu vreau ca operatorul să introducă manual rambursul.

---

# 25. Opțiunea PRODUSE

Dacă operatorul bifează:

```text
Produse
```

apare autocomplete.

Operatorul scrie:

```text
Polo
```

Ordely caută prin:

```text
CommerceConnector.searchProducts("Polo")
```

și afișează produsele din magazin.

După selectare:

```text
Produs:
Polo Premium

Mărime:
L

Culoare:
Navy

Cantitate:
1

Preț:
129 lei
```

Opțiunile și variantele trebuie preluate din CMS.

Se poate adăuga mai mult de un produs.

---

# 26. PRODUSE + TRANSPORT

Operatorul poate bifa simultan:

```text
☑ Produse
☑ Transport
```

Exemplu:

```text
Polo Premium L      129 lei
Transport            19 lei
────────────────────────────
TOTAL                148 lei
```

Ordely generează:

```text
Invoice = 148 lei

COD = 148 lei
```

Factura este emisă pe datele clientului din comanda originală.

---

# 27. Regula COD

Implicit:

```text
COD = Invoice Total
```

Operatorul nu trebuie să introducă manual valoarea COD pentru exchange workflow.

Exemplu:

```text
Invoice total:
148 lei

↓ automat

AWB COD:
148 lei
```

Această regulă reduce erorile.

---

# 28. Colet la schimb

La outbound shipment trebuie să existe:

```text
☐ Colet la schimb
```

DOAR dacă CarrierProvider declară că suportă funcția.

Dacă este activat:

providerul transmite opțiunea respectivă curierului.

Dacă acel curier nu suportă funcția:

UI-ul nu trebuie să o ofere sau trebuie folosit workflow-ul alternativ definit de Ordely.

---

# 29. Factura nouă

Dacă outbound shipment are:

```text
COD = 0
```

V1:

```text
Nu se generează factură.
```

Dacă există:

```text
Produse

sau

Transport
```

Ordely emite factura prin InvoiceProvider.

Datele clientului sunt luate automat din comanda originală:

```text
Customer
Billing Address
Company
CUI
Email
etc.
```

---

# 30. Relația obiectelor

Exemplu:

```text
Order #1254
│
├── Initial Invoice
│
├── Initial Shipment
│
└── Exchange EX-184
     │
     ├── Returned Items
     │
     ├── Return Shipment
     │
     ├── Requested Replacement
     │
     └── Outbound Shipment
          │
          ├── Products
          ├── Shipping Fee
          ├── Invoice
          └── COD
```

Totul trebuie să rămână legat de comanda originală.

---

# 31. Dashboard merchant

Module principale:

```text
Dashboard

Orders

Shipments

Returns

Exchanges

Refunds

Invoices

Integrations

Settings
```

Dashboard-ul trebuie să arate operațional:

```text
142 comenzi

126 expediate

8 retururi

4 schimburi

3 de rambursat

2 schimburi de expediat
```

---

# 32. Shopify integration

Merchantul instalează Ordely din Shopify App Store.

Ordely trebuie să poată apărea în Shopify Admin.

Ideal, în contextul unei comenzi să poată exista acțiuni precum:

```text
Facturează

Generează AWB

Retur

Schimb

Retrimite
```

Ordely poate sincroniza în Shopify:

```text
AWB

tracking

return status

exchange status

invoice status
```

Detaliile specifice Shopify trebuie să rămână exclusiv în ShopifyConnector.

---

# 33. Multi-tenant

Ordely este SaaS multi-tenant.

Model conceptual:

```text
Merchant
│
├── Users
│
├── Stores
│
├── Provider Connections
│
├── Orders
│
├── Returns
│
├── Exchanges
│
└── Settings
```

Un merchant trebuie să poată avea ulterior mai multe magazine.

---

# 34. Provider Connections

Credentials pentru:

```text
Sameday

FAN

Oblio

Shopify

etc.
```

nu trebuie puse direct pe Order sau Store.

Trebuie să existe concept generic:

```text
ProviderConnection
```

cu:

```text
merchantId
type
provider
credentials
settings
status
```

Credentials trebuie criptate.

---

# 35. Entități pe care arhitectura trebuie să le analizeze

Cel puțin:

```text
Merchant

User

Store

StoreBranding

ProviderConnection

Customer

UnifiedOrder

OrderItem

ProductReference

VariantReference

Shipment

ShipmentItem

ReturnCase

ReturnItem

ExchangeCase

ExchangeItem

OutboundShipment

Invoice

InvoiceLine

Refund

RefundDestination

WebhookEvent

Job

AuditLog

ExternalReference
```

ASTRA trebuie să decidă agregatele și relațiile corecte.

---

# 36. External References

Trebuie să putem mapa:

```text
Internal Order:
ord_123

Shopify Order:
gid://shopify/Order/...
```

La fel pentru:

```text
product

variant

customer

fulfillment

invoice

shipment
```

Trebuie proiectat un sistem generic ExternalReference.

---

# 37. Event-driven logic

Vreau events interne de tip:

```text
ORDER_IMPORTED

INVOICE_CREATED

SHIPMENT_CREATED

SHIPMENT_STATUS_CHANGED

RETURN_REQUESTED

RETURN_RECEIVED

EXCHANGE_CREATED

EXCHANGE_READY_FOR_OUTBOUND

OUTBOUND_SHIPMENT_CREATED

REFUND_READY

REFUND_COMPLETED
```

ASTRA trebuie să definească event model-ul.

---

# 38. Commands

Exemple:

```text
ImportOrder

CreateInvoice

CreateShipment

RequestReturn

ApproveReturn

CreateReturnShipment

ReceiveReturn

CreateExchange

CreateExchangeOutbound

ProcessRefund
```

ASTRA trebuie să separe clar commands de events.

---

# 39. Idempotency

Extrem de important.

Dacă operatorul apasă de două ori:

```text
Generează AWB
```

nu trebuie generate două AWB-uri.

Dacă Shopify trimite același webhook de două ori:

nu trebuie procesat de două ori.

Trebuie proiectată o strategie de idempotency.

---

# 40. Async jobs

Operațiuni precum:

```text
tracking sync

webhook processing

provider retry

invoice sync

status reconciliation
```

nu trebuie să blocheze request-urile HTTP.

Trebuie proiectat:

```text
Jobs
Workers
Retry
Backoff
Dead-letter/error handling
```

Compatibil cu PHP.

---

# 41. MySQL

Vreau ca ASTRA să proiecteze schema MySQL.

Trebuie să ofere:

- tabele;
- primary keys;
- foreign keys;
- indexes;
- unique constraints;
- JSON columns unde sunt justificate;
- soft deletes unde sunt necesare;
- status fields;
- timestamps;
- idempotency constraints.

Trebuie acordată atenție multi-tenancy.

Majoritatea entităților trebuie să poată fi limitate prin:

```text
merchant_id
```

sau `store_id`, după caz.

---

# 42. Security

Trebuie analizate:

```text
OAuth

API tokens

widget tokens

webhook signature validation

CSRF

XSS

SQL injection

rate limiting

provider credentials encryption

IBAN encryption

authorization

tenant isolation
```

Ordely nu stochează date de card.

---

# 43. Widget security

Nu trebuie să fie posibil ca cineva să schimbe pur și simplu Order ID-ul și să vadă alte comenzi.

Validarea trebuie să combine:

```text
Order ID
+
phone/email
```

și să permită OTP configurabil.

Widget-ul trebuie să utilizeze public store token + session/token temporar.

Nu trebuie expuse credentials ale CMS-ului în browser.

Browserul comunică doar cu API-ul Ordely.

---

# 44. Audit Log

Trebuie păstrat istoric.

Exemplu:

```text
10:32 Return requested

10:33 Return AWB created

11:04 Invoice created

15:23 Return picked up

Next day 09:12 Return received

09:18 Outbound shipment created
```

Audit-ul trebuie să precizeze:

```text
cine

ce

când

pe ce entitate

metadata relevantă
```

---

# 45. Error handling

Provider errors trebuie normalizate.

Nu vreau în UI:

```text
HTTP 422 SERVICE_INVALID
```

Vreau:

```text
Sameday nu permite acest serviciu pentru adresa selectată.
```

Payload-ul tehnic trebuie păstrat în logs.

---

# 46. V1

Prima versiune:

Commerce:

```text
Shopify
```

Carriers:

```text
Sameday
FAN Courier
```

Invoicing:

```text
Oblio
```

Funcții:

```text
Order sync

Invoice creation

AWB creation

Facturează + generează AWB

Tracking

White-label return widget

Order identification

Return for refund

IBAN collection

Refund queue

Size exchange

Product exchange

Inventory-aware variant selection

Return pickup

Return AWB

Return tracking

Receive return

Outbound exchange shipment

Nothing / Products / Shipping billing selection

Product autocomplete

Variant selection

Quantity selection

Automatic invoice total

Automatic COD = invoice total

Parcel exchange option where carrier supports it

Invoice for outbound shipment

Basic storno support

Dashboard

Audit log
```

---

# 47. După V1

Commerce connectors:

```text
WooCommerce

OpenCart

PrestaShop

Cartive

Magento
```

Carrier providers:

```text
DPD

Cargus

GLS

etc.
```

Invoice providers:

```text
SmartBill

FGO
```

Core-ul NU trebuie rescris pentru aceste integrări.

---

# 48. Filosofia UX

Operatorul trebuie să lucreze cu concepte de business.

NU:

```text
Create reverse fulfillment
```

CI:

```text
Ridică returul
```

NU:

```text
Set COD value
```

CI:

```text
Ce facturezi?

☐ Produse
☐ Transport
```

Ordely deduce automat suma și o transmite curierului.

NU:

```text
Create credit note
```

CI:

```text
Stornează
```

Complexitatea tehnică trebuie ascunsă.

---

# 49. Structură PHP

Vreau ca ASTRA să propună o structură PHP modulară.

Ca punct de pornire conceptual:

```text
/src

    /Domain

        /Orders
        /Shipping
        /Returns
        /Exchanges
        /Invoices
        /Refunds

    /Application

        /Commands
        /Queries
        /Services
        /Events
        /Handlers

    /Infrastructure

        /Database
        /Queue
        /Http
        /Security

    /Contracts

        CommerceConnector.php
        CarrierProvider.php
        InvoiceProvider.php

/connectors

    /Shopify
    /WooCommerce
    /OpenCart

/providers

    /Carriers
        /Sameday
        /FanCourier

    /Invoices
        /Oblio

/public

/widget

/workers
```

Aceasta NU este o cerință rigidă.

ASTRA trebuie să propună structura cea mai corectă pentru PHP și acest domain.

---

# 50. Frontend

Frontend:

```text
HTML
CSS
Vanilla JavaScript
```

Trebuie să existe cel puțin două UI-uri:

```text
Merchant Dashboard

Customer Return Portal
```

plus widget launcher.

Nu vreau framework frontend obligatoriu.

Codul trebuie să poată fi organizat în module JS native.

---

# 51. Ce vreau de la ASTRA ACUM

NU vreau să începi direct să scrii aplicația.

Mai întâi vreau PLANUL DE ARHITECTURĂ.

Livrează în ordine:

1. Domain model complet.

2. Bounded contexts / module boundaries.

3. Lista de entități și value objects.

4. Aggregate roots.

5. State machines pentru:
   - Shipment;
   - Return;
   - Exchange;
   - Refund;
   - Invoice.

6. CommerceConnector contract.

7. CarrierProvider contract.

8. InvoiceProvider contract.

9. Event model.

10. Command model.

11. Workflow-urile principale.

12. Arhitectura widget-ului.

13. Arhitectura multi-tenant.

14. MySQL ERD/schema propusă.

15. Indexes și constraints importante.

16. API REST endpoints.

17. Authentication / authorization.

18. Webhook architecture.

19. Queue / worker architecture pentru PHP.

20. Retry + idempotency strategy.

21. Audit/logging architecture.

22. Security model.

23. Structura folderelor PHP.

24. Strategia de integrare Shopify.

25. Cum va fi adăugat ulterior WooCommerce fără modificarea Core.

26. V1 implementation order.

27. Testing strategy.

28. Riscuri arhitecturale.

29. Ce decizii trebuie luate înainte de implementare.

30. Diagramă finală end-to-end.

După prezentarea planului, oprește-te.

Nu genera întregul cod al aplicației până nu este aprobată arhitectura.

Obiectivul final este:

ONE CORE
+
MULTIPLE COMMERCE CONNECTORS
+
MULTIPLE CARRIER PROVIDERS
+
MULTIPLE INVOICE PROVIDERS

iar experiența comerciantului trebuie să fie:

Install
↓
Connect Carrier
↓
Connect Invoicing
↓
Configure Returns Widget
↓
Start Working