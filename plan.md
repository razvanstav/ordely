# Plan Ordely

Actualizat: 2026-09-27. Registrul oficial al stărilor modulelor.

**Modul curent: 01 — Arhitectură. Stare: REVIEW.**

Arhitectura trebuie aprobată înainte de codul aplicației. Modulele următoare sunt ordonate, nu începute. Granularitatea lor poate fi ajustată la deschiderea fișei, păstrând un singur modul activ.

## Stări

`PLANNED` → `IN_PROGRESS` → `REVIEW` → `DONE`.

`BLOCKED` păstrează modulul ca unic modul curent. `REVIEW` înseamnă verificare/restanțe înainte de închidere. Doar modulul 01 are aprobarea explicită a arhitecturii cerută de brief; nu se introduc aprobări suplimentare pentru pașii de rutină deja autorizați.

## Ordine V1

Fiecare modul pornește după închiderea celui precedent. Testele de acceptare detaliate se fixează în fișa sa înainte de cod.

| Modul | Livrabil și limită | Dovada necesară la închidere | Stare |
| --- | --- | --- | --- |
| 01 | Arhitectură, decizii, workflow și predare | Cele 30 de secțiuni, verificare documentară, arhitectură aprobată | REVIEW |
| 02 | Mediu reproductibil PHP/MySQL, Composer, runner teste, CI și Git remote | Instalare din clonă curată și pe al doilea PC; smoke test + pipeline verde | PLANNED |
| 03 | Identitate, merchants, stores, roluri și tenant isolation | Acces între doi tenants respins la HTTP, repository și DB | PLANNED |
| 04 | Value objects, contracte și fake adapters | Core fără imports de furnizori; contract tests și bani exacți | PLANNED |
| 05 | Outbox, inbox, jobs, idempotency și audit | Concurență, restart worker, timeout ambiguu și retry fără dubluri | PLANNED |
| 06 | ProviderConnection, chei și registru integrări | Criptare/rotație testate; secrete absente din loguri și UI | PLANNED |
| 07 | Shopify: instalare, auth, webhook inbox și dezinstalare | Dev store conectat; semnături și revocare testate | PLANNED |
| 08 | Comenzi și catalog Shopify normalizate | Import repetat fără dubluri, reconciliere, paginare, variante/stoc | PLANNED |
| 09 | Facturare și Oblio, inclusiv storno de bază | Contract + integrare controlată; retry fără document duplicat | PLANNED |
| 10 | Shipping și Sameday | AWB, etichetă, anulare, retur, pickup și capabilities verificate | PLANNED |
| 11 | FAN Courier | Aceeași suită de contract; funcțiile sunt confirmate pentru contul folosit | PLANNED |
| 12 | Facturează + AWB + fulfillment + tracking | Reluare după eșec parțial fără reemiterea facturii/AWB-ului | PLANNED |
| 13 | ReturnCase și politica de eligibilitate | Retur parțial, concurență pe cantități, tranziții și inspecție | PLANNED |
| 14 | Portal comun, branding și identificare client | Izolare tenant/comandă, rate limit, sesiune fără cookies third-party | PLANNED |
| 15 | Launcher și Theme App Extension Shopify | Același portal; mobil, iframe, origin checks și fallback link | PLANNED |
| 16 | Ridicare, tracking și recepție retur | Traseu complet inbound, reconciliere și recepție fizică auditată | PLANNED |
| 17 | Refund queue, IBAN și confirmarea rambursării | Criptare, sume parțiale, dovadă plată, fără dublă rambursare | PLANNED |
| 18 | ExchangeCase și selecția înlocuitorului | Schimb mărime/produs, stoc revalidat, legături inbound/outbound | PLANNED |
| 19 | Expediere schimb/retrimitere și regulile de facturare | Nimic/Produse/Transport/ambele; COD calculat; colet la schimb verificat | PLANNED |
| 20 | Dashboard operațional, onboarding complet și configurări | Numărători corecte, filtre tenant/store, fluxuri accesibile | PLANNED |
| 21 | Pregătire pilot și lansare V1 | E2E, load/restore, observație, cerințe Shopify și proceduri validate | PLANNED |

Interfețele minimale necesare operării fiecărui modul se construiesc în modulul respectiv. Modulul 20 unifică dashboard-ul; nu amână până atunci validarea fluxurilor cu operatorul.

## După V1

WooCommerce → OpenCart/PrestaShop/Cartive/Magento după prioritizare; DPD/Cargus/GLS; SmartBill/FGO; Redis; politici fiscale extinse; automatizare plăți; billing SaaS dacă nu este cerut pentru distribuția aleasă. Fiecare intră ca modul separat, după contract tests.

## Regula de închidere

- Criteriile modulului sunt îndeplinite și testele obligatorii au rezultate consemnate.
- Nu există defecte blocante ascunse în jurnal; limitările acceptate sunt explicite.
- Fișa, planul, starea, jurnalul și deciziile sunt actualizate în aceeași schimbare.
- Commit/push sunt verificate dacă remote-ul este configurat; altfel starea locală nesincronizată este vizibilă.
- Predarea spune exact ce trebuie citit și executat la reluare.
