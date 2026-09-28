# Plan Ordely

Actualizat: 2026-09-29. Registrul oficial al stărilor modulelor.

**01–07 DONE. 08 REVIEW — reluarea probelor Shopify cerută de utilizator. 09 PAUSED la finalul pasului 09.1; 09.2 nu a început.**

Ultimul pas 08, 2026-09-29: fixture creată o singură dată prin client_credentials, ID 8239905505617, 30 linii/36,00 RON; query-uri reale, paginare 25+5, date sintetice, normalizare și criptare verificate de două ori. Corectat read_customers lipsă; write_orders retras și verificat. 58 teste/286 assertions și 6 probe HTTP PASS. Importul/reimportul persistat rămâne NOT_RUN până la legarea App Bridge pe acest PC. Preview/PHP/tunel/MySQL oprite; datele păstrate. Nu recrea fixture-ul. [Predare](STATUS.md), [raport](docs/testing/08-commerce-import.md).

09.1 închis: 207 teste locale, UI/API pentru ciorne manuale, migrația 007, criptare/revizii, sume exacte și concurență. Cod f622aab publicat cu hash remote verificat și CI Windows/Linux 36480973254 PASS; [fișa 09](docs/modules/09-invoicing.md). Următorul pas al facturării, după reluarea 08: 09.2; 10 nu este început.

Reluare pe PC-ul curent la 2026-09-28: predarea `807e0da` sincronizată fără conflicte, migrațiile 005/006 aplicate și toate cele 190 de teste rerulate cu PASS. Configurarea/tokenurile Shopify de pe celălalt PC nu vin prin Git; pregătirea locală este consemnată în STATUS. Criteriul comenzii reale rămâne deschis, amânat explicit prin cererea de continuare fără login Shopify.

Arhitectura a fost acceptată pentru continuare prin mesajul utilizatorului „CONTINUA”. Continuăm același branch de lucru `codex/modul-01-arhitectura`, fără fragmentarea istoricului între PC-uri.

Utilizatorul a autorizat modulele 03–06, executate strict în ordine: implementare, teste, documentare, commit/push, apoi următorul. Deciziile D01–D05 se închid înainte de modulele care le folosesc. Lotul 03–06 este închis, fiecare modul fiind publicat și verificat în CI înainte de următorul. La 2026-09-28, „go” autorizează continuarea cu modulul 07. 07 este închis după reinstalarea finalizată de utilizator și verificarea reconectării/refresh-ului. Mesajul „Ok, te rog.” autorizează 08; implementarea și catalogul real sunt verificate, codul e8a4133 este publicat cu CI 36420254697 PASS Windows/Linux. Ulterior, „Ai acordul meu” autorizează write_orders CLI și comanda sintetică; Install rămâne blocat de auto-review până la confirmarea explicită a datelor personale afișate de Shopify. A treia rulare a catalogului confirmă refresh automat după expirarea accesului, fără dubluri. Ulterior, utilizatorul a cerut continuarea fără probele Shopify care cer login. Amânăm proba reală 08 și continuăm secvențial cu partea locală 09; testele automate fără cont extern rămân obligatorii. 10–21 nu sunt începute.

La cererea nouă „hai și cu testele shopify”, reluăm exclusiv 08. Utilizatorul a confirmat explicit CLI Connector App/write_orders și datele clienților/proprietarului numai pe Ordely Shop dev. 09.1 rămâne închis și publicat; 09.2 așteaptă finalizarea probei 08.

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
| 08 | Comenzi și catalog Shopify normalizate | Import repetat fără dubluri, reconciliere, paginare, variante/stoc | REVIEW |
| 09 | Facturare și Oblio, inclusiv storno de bază | Contract + integrare controlată; retry fără document duplicat | PAUSED |
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
