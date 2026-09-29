# Plan Ordely

Actualizat: 2026-09-29. Registrul oficial al stărilor modulelor.

**01–08 DONE. 09 IN_PROGRESS, unicul activ: pregătirea 09.2 după răspunsul „ok”. 09.1 rămâne închis; codul 09.2 nu este încă început.**

Închidere 08, 2026-09-29: Git sincronizat la `bea3206`; conexiunea App Bridge existentă reînnoită real (v4→v5), cinci scope-uri de citire. Import/reimport HTTP + worker PASS: fixture existentă 8239905505617, 30 linii/36,00 RON, paginare 25+5, criptare DB/staging, publicare atomică, ID-uri/versiuni/hash-uri stabile și un singur ORDER_IMPORTED. Catalog 17/26/28 stabil. 207 teste, 213 lint, PHPStan 8, JS și 6 probe HTTP PASS. MySQL/preview rămân pornite. [Predare](STATUS.md), [raport](docs/testing/08-commerce-import.md).

Corecția 08 `7e87d9e` este publicată; [CI Windows/Linux 36486355856 PASS](https://github.com/razvanstav/ordely/actions/runs/36486355856), reverificat la închidere. Proba persistată restantă este acum PASS. Această predare modifică numai documentația versionată.

09.1 închis: 207 teste locale, UI/API pentru ciorne manuale, migrația 007, criptare/revizii, sume exacte și concurență. Cod f622aab publicat cu hash remote verificat și CI Windows/Linux 36480973254 PASS; [fișa 09](docs/modules/09-invoicing.md). Următorul pas al facturării, după reluarea 08: 09.2; 10 nu este început.

Istoric 2026-09-28: probele externe 08 au fost amânate explicit pentru a continua 09.1 fără login Shopify (D15). Istoricul detaliat și autorizările sunt în jurnal și D17. Amânarea și blocajul tehnic de pe PC-ul secundar sunt închise prin probele persistate de pe PC-ul inițial.

Arhitectura a fost acceptată pentru continuare prin mesajul utilizatorului „CONTINUA”. Continuăm același branch de lucru `codex/modul-01-arhitectura`, fără fragmentarea istoricului între PC-uri.

Modulele 03–06 au fost autorizate și închise strict secvențial, apoi 07 și 08. Acordurile Shopify dev și crearea unică a fixture-ului sunt consemnate în D17. write_orders a fost retras; nu se recreează comanda. 10–21 nu sunt începute.

Reluarea cerută prin „Cloneaza ce e pe github si hai sa continuam” a închis proba 08. 09.1 rămâne păstrat; 09.2 se reia separat, cu D01/D08 și criterii definite înainte de implementare.

## Stări

`PLANNED` → `IN_PROGRESS` → `REVIEW` → `DONE`. `DEFERRED_EXTERNAL` înseamnă probe externe amânate explicit de utilizator, fără declarație de validare completă; nu ocupă locul activ.

`PAUSED` păstrează progresul unui modul întrerupt la cererea utilizatorului și nu ocupă locul activ. `BLOCKED` păstrează modulul ca unic modul curent. `REVIEW` înseamnă verificare/restanțe înainte de închidere. Doar modulul 01 are aprobarea explicită a arhitecturii cerută de brief; nu se introduc aprobări suplimentare pentru pașii de rutină deja autorizați.

## Ordine V1

Fiecare modul pornește după închiderea celui precedent sau după amânarea explicit autorizată a probelor lui externe. Testele de acceptare detaliate se fixează în fișa sa înainte de cod.

| Modul | Livrabil și limită | Dovada necesară la închidere | Stare |
| --- | --- | --- | --- |
| 01 | Arhitectură, decizii, workflow și predare | Cele 30 de secțiuni, verificare documentară, arhitectură aprobată | DONE |
| 02 | Mediu reproductibil PHP/MySQL, Composer, runner teste, CI și Git remote | Clonă curată + CI Windows/MySQL nativ și Linux/Compose/HTTP verzi | DONE |
| 03 | Identitate, merchants, stores, roluri și tenant isolation | Acces între doi tenants respins la HTTP, repository și DB | DONE |
| 04 | Value objects, contracte și fake adapters | Core fără imports de furnizori; contract tests și bani exacți | DONE |
| 05 | Outbox, inbox, jobs, idempotency și audit | Concurență, restart worker, timeout ambiguu și retry fără dubluri | DONE |
| 06 | ProviderConnection, chei și registru integrări | Criptare/rotație testate; secrete absente din loguri și UI | DONE |
| 07 | Shopify: instalare, auth, webhook inbox și dezinstalare | Dev store conectat; semnături și revocare testate | DONE |
| 08 | Comenzi și catalog Shopify normalizate | Import repetat fără dubluri, reconciliere, paginare, variante/stoc | DONE |
| 09 | Facturare și Oblio, inclusiv storno de bază | Contract + integrare controlată; retry fără document duplicat | IN_PROGRESS |
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
