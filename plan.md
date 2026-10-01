# Plan Ordely

## Lot curent — 09.2c.1 livrat, 2026-10-01

Contract fiscal neutru + reconciliere exactă + mapper Oblio offline implementate conform D27. GET/UI ciornă arată net/taxe/total și erori; nu ascunde diferențe de un ban și nu deduce cota/netul unitar. Firma/seria/cota cer nomenclator verificat de viitoarea operație, fără scriere externă acum. canIssue=false; contractul legacy rămâne compatibil. PASS: 257 teste, 249 lint, PHPStan 8, JS/HTTP și browser. [Raport](docs/testing/09-invoicing.md).

Reluare: 09.3a, schema/operația durabilă de emitere, cu revalidare merchant/store/profil și cheie stabilă; apoi apel/răspuns/reconciliere externă, original/storno/PDF. Extinderi restante ale 09: transport fiscal complet, tratamente speciale TVA, valută. Aceste limite rămân explicite și nu țin loc de funcționalitate livrată. Singurul activ 09 IN_PROGRESS; 10–21 PLANNED. Probe reale mutate la final prin D26, fără chei noi cerute acum; stoc separat. Git/push/CI se consemnează la predare.

## Pas curent — 2026-10-01

Lot `58b200b` publicat; push/fetch și egalitatea HEAD/origin PASS. CI observat IN_PROGRESS, rezultat final neverificat; verificările locale de mai jos sunt PASS. Predarea ulterioară este numai documentară.

Continuare secvențială autorizată prin D26: dezvoltare și verificări locale pe modul, probe reale de API/provider mutate în [registrul final](docs/testing/final-integrations.md). 09 rămâne IN_PROGRESS pentru funcțiile sale restante; 10–21 PLANNED. Probe externe amânate nu vor împiedica trecerea când implementarea și verificările locale sunt încheiate; nu certificăm providerul din teste simulate.

09.2a.2.2b implementat: completări fiscale explicite persistate în snapshot-ul criptat, versiuni/retry, protecție la sursa schimbată, refresh păstrează completările aplicabile și acordă prioritate datelor CMS. Formular cu valori comune/excepții pe produse și stări READY_FOR_MAPPING/canIssue=false. Firma/seria și sumele rămân server-side. PASS: 251 teste, 240 lint, PHPStan 8, 3 JS, 6 HTTP și Chrome desktop/mobil. [Probe](docs/testing/09-invoicing.md).

Următorul lot: 09.2c — contract fiscal neutru complet, reconciliere exactă preț/reducere/taxă/total și mapare pentru adaptor; apoi 09.3 — operații durabile. Mediul D08 și probele externe de emitere/storno/PDF rămân NOT_RUN pentru final. Stoc management separat/amânat. [Predare](STATUS.md).

Publicare schelet D25: cod `27f544a` sincronizat pe `codex/modul-01-arhitectura`; push/fetch PASS, HEAD/origin identice și checkout curat verificate. Verificările locale ale lotului au trecut; CI este declanșat automat la push și nu este declarat PASS fără rezultat. Nu s-au adăugat teste noi pentru ecranele de structură. Această predare modifică numai documentația.


## Scheletul întregii aplicații — 09-S livrat

2026-09-30: la cererea explicită a utilizatorului, prioritatea este structura întregii aplicații (D25), înaintea aprofundării facturării. Livrate 9 ecrane noi și navigare: expediții, tracking, retururi, refuzuri, schimburi, retrimiteri, rambursări, administrare portal și setări. Registru/componente comune, filtre, pași, câmpuri și legături între fluxuri; Acasă arată harta aplicației. Contextul magazinelor vine din sesiunea existentă; acțiunile viitoare rămân dezactivate, fără date fabricate sau salvări simulate. Folderele Shipping/Returns/Exchanges/Refunds/Portal sunt pregătite pe patru straturi; contractele Core sunt păstrate.

Verificat: sintaxe workspace.js/app.js/invoicing.js, HTTP 6 probe și o singură regresie existentă PASS (246 teste, 238 lint, PHPStan 8). Niciun test nou pentru ecranele de structură. Chrome owner: toate cele 9 rute, reload, legătură Schimburi → Retururi și Back, desktop/mobil 390×844 fără overflow, câmpuri/acțiuni dezactivate și consola fără erori. Afișarea hărții Acasă a fost corectată după proba inițială; verificarea finală confirmă numai ecranul activ. Screenshot local ignorat. Probe provider și autorizarea vizuală a altor roluri NOT_RUN în acest lot.

Reluare: structura transversală este gata; conectăm fluxurile reale în loturi coerente, fără o conversație/commit pentru fiecare detaliu de UI. Facturarea existentă rămâne disponibilă; restanțele 09.2a/c/09.3 se păstrează. 09 rămâne singurul IN_PROGRESS; 10–21 PLANNED pentru implementarea funcțiilor, cu structura pregătită. Nu se consideră modulele terminate din existența ecranelor. Stoc management rămâne separat/amânat. Publicarea se verifică la finalul lotului.


Publicare 09.2a.2.2a: cod `4350a86` trimis pe `codex/modul-01-arhitectura`; push, fetch și HEAD/origin identice verificate, checkout curat. [CI Windows/Linux 36707834906 PASS](https://github.com/razvanstav/ordely/actions/runs/36707834906), `gh run watch 36707834906 --exit-status --interval 15` exit 0: PHP/MySQL pe ambele sisteme, migrare/restart Windows și HTTP Linux. Regresie locală 246 teste, 238 lint, PHPStan 8, două sintaxe JS și 6 HTTP PASS. Linkuri/diff/staged scanner pentru secrete/date fiscale PASS. Captura nativă finală `var/ui-order-draft-20260930.jpg` este locală și ignorată. Această predare ulterioară modifică numai documentația; emitere/storno/PDF rămân NOT_RUN.


## Ciornă din comandă CMS — 09.2a.2.2a implementat

2026-09-30: modulul 09 rămâne unicul IN_PROGRESS. Comenzi → Pregătește facturarea → Salvează ciorna din comandă păstrează local raportul CMS inclusiv incomplet, separat de ciornele manuale. Redeschiderea arată snapshot-ul salvat; schimbările comenzii/profilului/conexiunii sunt semnalate, actualizarea și salvarea sunt explicite. Serverul recitește sursa, verifică versiunile și accesul; criptare/AAD, CAS/retry, audit atomic și inventar chei. Migrația 009 aplicată în app/test. Propunerea inițială se elimină la ștergerea sursei CMS; nu este registrul viitoarelor documente fiscale (D24).

PASS local: 246 teste (119 unit/826 assertions, 127 integration/839), 238 lint, PHPStan 8, două sintaxe JS și 6 HTTP. Două procese concurente, rollback, privacy, profil invalidat, roluri/granturi/CSRF/izolare și criptare testate. Chrome: salvare a comenzii sintetice existente, 30 linii/36.00 RON, reload/redeschidere și resalvare fără dubluri (revizia 1), Enter/focus, filtrare cu ștergerea datelor, desktop/mobil 390×844 fără overflow, consola fără erori. [Probe](docs/testing/09-invoicing.md).

Reluare exactă: Facturare are o ciornă din comanda de test, salvată local. Continuă 09.2a.2.2b: completările fiscale persistate (emitent, destinatar, adrese, unități/tratamente TVA, date document) și snapshot-ul fiscal neutru. Lipsurile comenzii de test nu cer completare manuală acum; datele disponibile vor veni din Shopify. Firma/seria emitentului rămân din profilul Oblio. canIssue=false; emiterea/storno/PDF și mediul D08 sunt NOT_RUN/restante. 10–21 rămân PLANNED. Publicarea Git și CI se consemnează după verificare.


Publicare 09.2a.2.1: cod `6556a4f` trimis pe branch-ul proiectului, HEAD/origin identice și checkout curat verificate. [CI Windows/Linux 36701852570 PASS](https://github.com/razvanstav/ordely/actions/runs/36701852570), urmărit cu `gh run watch 36701852570 --exit-status --interval 10` până la exit 0. Regresie locală: 240 teste, 232 lint, PHPStan 8, două sintaxe JS și 6 probe HTTP PASS. Linkuri relative, diff/staged check și scanner staged pentru secrete/date fiscale PASS. Această predare ulterioară modifică numai documentația; nu pretinde o nouă probă de emitere.

## Pas curent — 09.2a.2.1 implementat

2026-09-30: 09 unicul IN_PROGRESS. Pregătirea numai de citire a facturării dintr-o comandă CMS este disponibilă în Facturare: date de facturare, profil, sume/linii exacte și lista lipsurilor. Izolare/roluri/granturi, profil invalidat și audit fără date personale testate; 9 teste noi PASS, PHPStan 8 și Chrome desktop/mobil PASS. [Raport și regresie finală](docs/testing/09-invoicing.md). Nu există emitere, completări salvate sau scrieri de stoc. Urmează 09.2a.2.2, completările persistate și snapshot fiscal legat de versiunea comenzii/profilului. 09.2a.2 nu este încă închis; 10–21 rămân PLANNED.

## Reguli acceptate — 2026-09-30

D01 — reguli de produs acceptate: diferența de preț la schimb se introduce manual, fără compensare automată. Bifa Transport preia tariful configurat în site și facturează pe datele inițiale ale clientului; AWB se generează automat cu COD egal cu diferența manuală plus transportul selectat. Nimic/total zero la schimb sau retrimitere înseamnă fără factură nouă/COD 0; regula nu elimină factura unei comenzi obișnuite deja plătite.

Storno automat numai la înregistrarea unui refuz de primire, pe original verificat; returul obișnuit nu declanșează storno. Facturile externe se acceptă numai după identificare/verificare în Oblio; original neverificabil blochează storno. Refuzul repetat nu dublează storno; rezultat necunoscut cere reconciliere conform D11. Planul nu avea un modul distinct pentru refuzuri: fluxul este păstrat în backlog-ul 16, corelat cu tracking/recepție, fără implementarea lui acum.

D04 — gestiunea stocului, reintegrarea, ajustările și rezervările sunt amânate explicit pentru o zonă separată. Oblio va emite fără modificări de stoc; citirea disponibilității rămâne separată. Email și SPV fără trimitere automată în etapa de test, acceptat. Mediul de emitere D08, validarea fiscală și probele reale de emitere/storno rămân restante. Aceste decizii actualizează propunerile istorice de mai jos; nu certifică providerul.

Reluare: 09.2a.2 — date complete și lista lipsurilor; 09 rămâne singurul modul activ. Schimbare numai documentară; fără emitere sau teste runtime rerulate.


Actualizat: 2026-09-30. Registrul oficial al stărilor modulelor.

**01–08 DONE. 09 IN_PROGRESS, unicul activ: 09.2a.1 finalizat — firmă/serie salvate criptat pe magazin, rezumat în Facturare și proba reală de salvare/reload PASS (D22). Urmează 09.2a.2, datele complete și verificarea pregătirii facturii. 09.1, 09.2b și UI D21 sunt păstrate.**

09.2a.1: migrația 008 în app/test, API cu revalidare live înainte de salvare, CAS/retry, izolare merchant/store și audit fără conținut fiscal; invalidare la schimbarea conexiunii. PASS 231 teste, 226 lint, PHPStan 8, JS/HTTP și Chrome desktop/mobil. D01/D08 pentru emitere rămân deschise; 09.2c/09.3 și 10–21 neîncepute. [Probe](docs/testing/09-invoicing.md). Nu există snapshot fiscal complet sau documente emise.

Cod `45ac9ba` și corecția de test `1fcf8e4` publicate, hash local/origin identic și [CI Windows/Linux 36687850355 PASS](https://github.com/razvanstav/ordely/actions/runs/36687850355). Proba de rollback funcționează fără trigger privilegiat; permisiunile DB nu au fost schimbate. Predarea ulterioară este documentară.

UI 2026-09-30 finalizat: catalog Facturare/Curierat/Magazine online, detalii numai pentru serviciul selectat, pași lizibili Oblio și liste serii/TVA, cont nou la cerere, acces/administrare separate. Curierii planificați sunt indisponibili; 10/11/20 nu sunt implementate. Desktop/mobil, navigare și citire reală Oblio PASS; 220 teste, lint/PHPStan/JS/HTTP PASS. [Probe](docs/testing/09-invoicing.md). Urmează profilul și datele complete 09.2a; D01/D08 pentru emitere rămân deschise.

UI `a357c81` publicat, hash local/remote identic și [CI Windows/Linux 36681823310 PASS](https://github.com/razvanstav/ordely/actions/runs/36681823310), urmărit până la final. Predarea ulterioară este numai documentară.

Operare 2026-09-30: cont local personal creat la cerere în spațiul existent; hash și login real în Chrome PASS. Ulterior, datele API furnizate de utilizator au fost salvate prin UI: o conexiune Oblio criptată, asociată magazinului, versiunea 2. Autentificare și citire reală PASS: 1 firmă, 1 serie de factură, 10 cote TVA; audit sigur și persistare criptată verificate în DB. 09.2b este închis, fără profil fiscal salvat sau documente emise. D18 actualizat; D01 și condițiile de emitere D08 rămân deschise. D20 consemnează accesul local. Nu există hosting nou; fără schimbări runtime sau module noi.

Utilizatorul a confirmat continuarea locală, fără hosting în acest pas.

Pas UI cerut de utilizator, 2026-09-29, finalizat local: suprafețele existente grupate în șase pagini, Acasă cu date reale și pași de configurare, formulare la cerere și diagnostic separat. Desktop/mobil, navigare, calcul ciornă, 220 teste, lint/PHPStan/JS/HTTP PASS; [raport](docs/testing/09-invoicing.md). D19 consemnează extinderea în 09; 20 rămâne PLANNED. Conectarea Oblio planificată atunci a fost verificată ulterior, la 2026-09-30. Nu s-au apelat furnizorii în proba UI din 2026-09-29.

UI `b1709f0` publicat, HEAD/remote identice și [CI Windows/Linux 36575021341 PASS](https://github.com/razvanstav/ordely/actions/runs/36575021341). Predarea ulterioară este numai documentară.

Istoric 09.2b, 2026-09-29: utilizatorul confirmă contul și autorizează integrarea locală; 220 teste, 222 lint, PHPStan 8, JS și HTTP PASS. Formular verificat în Chrome; valorile mascate au împiedicat proba reală la acel moment. Proba restantă este acum PASS conform operării din 2026-09-30. 09.2a/c și 09.3 rămân de implementat, D01 și mediul de emitere D08 deschise. [Fișa 09](docs/modules/09-invoicing.md), [raport](docs/testing/09-invoicing.md).

Codul 09.2b `11dddf4` este publicat, hash remote verificat și [CI Windows/Linux 36551856411 PASS](https://github.com/razvanstav/ordely/actions/runs/36551856411). 09 rămâne IN_PROGRESS.

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
| 16 | Ridicare, tracking, recepție retur și refuz de primire cu declanșare storno | Traseu complet inbound, reconciliere și recepție fizică auditată | PLANNED |
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
