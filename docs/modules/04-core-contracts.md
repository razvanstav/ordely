# Modul 04 — Value objects, contracte și fake adapters

Actualizat: 2026-09-27. Stare: **DONE**. Dependențe: 01–03 DONE; 03 publicat și CI verde înaintea acestui modul.

## Criterii înainte de cod

- [x] Money în unități minore, monedă explicită, parsing decimal fără float, limite/overflow, rotunjire explicită și alocare care păstrează totalul.
- [x] ID-uri și cantități tipate, adrese/snapshots normalizate și DTO-uri imutabile, fără obiecte de SDK.
- [x] Porturi CommerceConnector (compus din porturi mici), CarrierProvider și InvoiceProvider conform arhitecturii §06–08; capabilities și erori tipate, inclusiv unsupported și rezultat necunoscut.
- [x] Adaptoare fake de dezvoltare/test, cu date separate după merchant/store/connection, paginare și efecte repetabile după OperationKey; aceeași cheie cu alt conținut este conflict.
- [x] Contract tests prin interfețe, teste negative de scope/capabilities și dependențe: Core nu importă HTTP, DB sau providers.
- [x] Teste complete, documente, commit/push și CI verificate înainte de 05.

## Limite

Nu se implementează importul comenzilor, politicile fiscale/retur, workflow comercial sau provider real. Fake-urile nu demonstrează idempotency durabilă ori comportament Shopify/FAN/Sameday/Oblio. Durabilitatea este modulul 05, conexiunile/cheile 06, adaptoarele reale 07+.

## Pași și predare

1. Value objects și DTO-uri cu invariante.
2. Porturi, capabilities, erori și adaptoare fake.
3. Teste locale PASS: 65 unit/505 assertions, 17 integration/90 assertions, 108 lint și PHPStan level 8. [Raport](../testing/04-core-contracts.md), [contracte](../core-contracts.md). Cod `5e0b747`, push verificat, CI Windows/Linux PASS: https://github.com/razvanstav/ordely/actions/runs/36348452800.

Branch: `codex/modul-01-arhitectura`. 04 este închis; urmează 05 conform autorizării, cu fișă și criterii înainte de cod.
