# Modul 08 — Import comenzi și catalog

Stare: DONE, 2026-09-29. Importul/reimportul persistat a trecut pe PC-ul inițial, cu conexiunea App Bridge autentică existentă și worker real. 09 este PAUSED după 09.1; 09.2 este următorul pas separat.

## Obiectiv și limită

Pas de reluare pe PC-ul inițial, 2026-09-29: Git sincronizat la `bea3206`; conexiunea App Bridge autentică din 07 există local, cu refresh neexpirat. Verificăm reînnoirea ei cu cele cinci scope-uri de citire, apoi import/reimport HTTP + worker al fixture-ului existent. Acceptare: 30 linii/3600 bani RON, date sintetice corecte și criptate în DB, paginare 25+5, ID-uri/versiuni/hash stabile, un singur ORDER_IMPORTED și staging gol la final. Refolosim conexiunea existentă dacă reînnoirea reușește; nu recreăm fixture-ul.

Import reluabil, normalizat și izolat pe merchant/store din Ordely Shop (dev), folosind autentificarea 07 și jobs 05. Include comenzi/linii/bani/statusuri/client, produse/variante/opțiuni/stoc, reconciliere și UI. Exclude scrieri de stoc, rezervări, facturi, AWB și fulfillment. ANATOMIK live nu se folosește.

## Criterii de acceptare

- [x] Contract de import independent de Shopify și normalizare fără calcule monetare float.
- [x] Migrație 006 cu identitate externă unică, ID-uri interne stabile și FK compuse.
- [x] Query-uri validate, erori/rate limit și răspunsuri parțiale tratate explicit.
- [x] Paginare durabilă inclusiv linii, variante și locații; retry/restart, atomicitate și concurență testate.
- [x] Import repetat fără dubluri și ORDER_IMPORTED fără duplicare; watermark numai la succes.
- [x] Reconciliere catalog/stoc, cantități negative/necunoscute distincte; fără rezervări.
- [x] Izolare tenant/store/conexiune, sesiune/CSRF/roluri la API și guard în jobs înainte de commit.
- [x] Criptare comenzi/staging și inventarul cheilor; privacy export/confirmare/redact cu blocarea reimportului.
- [x] UI: start/progres/listare/detalii/paginare și privacy pentru operator autorizat.
- [x] Suite automate obligatorii: bani, paginare, concurență, retry, revocare, privacy și tenant isolation.
- [x] Catalog importat real de două ori pe Ordely Shop, fără dubluri sau versiuni nejustificate.
- [x] Cod publicat în Git și CI Windows/Linux PASS pentru `e8a4133` (run 36420254697).
- [x] Comandă sintetică reală creată o singură dată: ORDELY-TEST-M08, ID 8239905505617, 30 linii, 36,00 RON; fără plăți/notificări/stoc.
- [x] Query-uri reale și normalizare în două pagini 25+5, total/date sintetice și OrderCipher verificate; recitire cu același hash.
- [x] read_customers necesar order.customer.id adăugat; write_orders retras și verificat prin API.
- [x] Import/reimport persistat prin conexiunea App Bridge existentă, HTTP și worker; ID-uri interne/versiuni/hash-uri/evenimente stabile.
- [x] Probele complete, CI pentru codul publicat și documentele de închidere verificate; commit/push documentar la predare conform workflow-ului.

## Închidere și punct exact de reluare

Refresh real al conexiunii originale din 07: v4→v5, cinci scope-uri de citire. Import HTTP autentic + 47 jobs: run `ddd8da2b13fdf6b9a0881a2315d16bc9`, completed 05:45:22 UTC. Reimport: run `25a18ae2afc5b1b761ebd11e09341227`, 3+44 jobs, completed 05:47:41 UTC. Fiecare rulare: 46 tasks, zero eșecuri. Nicio simulare App Bridge și niciun token client_credentials introdus în DB.

Comandă internă `ba49d8549742be78f6436a0f6b2dc3a5`, 30 linii/3600 bani RON; ID-urile/versiunile/hash-urile tuturor proiecțiilor sunt identice după reimport, un singur ORDER_IMPORTED. Oprirea după trei jobs a confirmat 25 linii criptate în staging și proiecția publicată completă, neschimbată; reluarea a publicat 30 și a golit staging-ul. Sumele și datele sintetice verificate prin endpointul HTTP de detalii.

Git inițial curat, fast-forward `807e0da`→`bea3206`. Migrația 007 aplicată app/test; 207 teste și CI existent PASS, fără schimbare de cod/config. MySQL/preview rămân pornite. Helper-ele `var/resume-08-*` sunt locale, ignorate. Inspecția vizuală a comenzii nu s-a repetat; probele HTTP și UI anterioare sunt distincte.

> 08 DONE. La reluare verifică Git, STATUS și plan. Următorul pas este 09.2: reactivează exclusiv 09, citește fișa 09 și D01/D08/D16, fixează criteriile pentru date complete de emitere și adaptorul Oblio, clarifică regulile/contul/seriile înainte de emitere. Nu recrea fixture-ul Shopify, nu readăuga write_orders și nu începe 10. Istoricul de mai jos nu înlocuiește această predare.

## Verificări

La închidere: 207 teste PASS (106 unit/707 assertions, 101 integration/634 assertions), 213 lint, PHPStan 8, JS/config Shopify valide și 6 probe HTTP. Catalog 17 produse/26 variante/28 inventare și o comandă sintetică de 30 linii/36,00 RON. CI Windows/Linux pentru ultimul cod `7e87d9e` reverificat PASS. [Raport și comenzi](../testing/08-commerce-import.md), [operare](../commerce-import.md).

## Decizii și istoricul probei amânate

D04: numai citire pe locații în 08. D14: proiecții separate, staging atomic, retenție temporară șapte zile, privacy locală. D17: cinci scope-uri de citire și câmpurile sintetice confirmate live, inclusiv în DB. Aprobările de producție rămân distincte.

Istoric înainte de D17: auto-review a cerut confirmarea datelor personale. Acordurile au fost ulterior primite explicit; fixture-ul a fost creat o singură dată, scrierea retrasă și importul persistat verificat. Acest blocaj nu mai este activ.

## Predare anterioară — înlocuită pentru continuarea curentă

Reluare pe PC-ul curent, 2026-09-28: fetch/pull până la `807e0da`, dependențe și migrații actualizate, suita completă 190 teste și HTTP smoke PASS. Preview local pornit. Lipsesc configurarea Shopify și contul sintetic local din predarea celuilalt PC; DB/tokenurile/keyring-ul lui nu sunt în Git. Rezolvă acest setup înainte de proba reală; aprobarea restantă pentru CLI Install nu a fost ocolită. Starea rămâne REVIEW.

- Ultimul pas: a treia rulare reală confirmă refresh automat după expirare (conexiune v4), catalog 17/26/28 fără dubluri; CLI Install blocat separat. CI Windows/Linux PASS pentru cod `e8a4133`.
- Următorul pas: confirmarea exactă a datelor din Install CLI numai pe dev store; apoi lookup fixture, creare unică, test real și închidere.
- Branch: codex/modul-01-arhitectura; codul și documentele publicate în `e8a4133`, urmate de predarea documentară cu rezultatul CI. Verifică Git la reluare.
- Reluare: „Continuă exclusiv modulul 08. Citește STATUS, raportul 08 și D14. Write_orders și comanda sunt aprobate; rezolvă confirmarea datelor personale cerută de auto-review la Install. Verifică întâi să nu existe deja ORDELY-TEST-M08. Nu începe 09.”
## Decizia anterioară — amânarea

Utilizatorul: „Hai să continuăm fără testele de Shopify. Că nu pot să mă loghez.” Nu se mai cere login și nu se reîncearcă instalarea CLI sau crearea comenzii acum. Proba celor 30 de linii/PCD/reimport rămâne NOT_RUN/DEFERRED și se reia separat când accesul este disponibil. Următorul pas autorizat este partea locală a modulului 09. Rezultatele automate și cele trei importuri reale de catalog rămân dovezi istorice valide.

## Reluare inițială — 2026-09-29, înainte de continuarea autonomă

Utilizatorul a autorizat explicit CLI Connector App și datele personale afișate. Autentificările CLI au reușit, dar orderCreate necesită token offline; CLI folosește online. Lookup ulterior confirmă zero fixture. Utilizatorul a autorizat separat actualizarea preview-ului Ordely și write_orders temporar pentru aceeași comandă, urmat de retragerea scrierii. Preview pornit; .env/keyring și cont sintetic locale, fără secrete în Git. Codul de legare există în var/shopify-link-code.txt, cu TTL 10 minute. Controlul browserului are eroare sandbox ACL; utilizatorul finalizează legarea în aplicația Shopify. Nu se modifică ANATOMIK live.

Această etapă a fost urmată de probele de mai jos; nu mai este necesară crearea fixture-ului.

## Predare de pe PC-ul secundar — istoric, înlocuită de închiderea de mai sus

Acordul utilizatorului acoperă automatizarea și accesul Ordely dev; nu lipsesc aprobări. Browserul rămâne indisponibil tehnic. Client_credentials a funcționat legitim pentru aplicația și dev store-ul aceleiași organizații, permițând crearea fixture-ului și probe directe de citire. Nu este o simulare a App Bridge și nu a creat artificial o conexiune în DB. Modulul păstrează autentificarea embedded existentă.

Proba reală a găsit ACCESS_DENIED pentru customer.id fără read_customers. Configurația a fost corectată și aplicată pe dev; query-urile nemodificate au trecut apoi de două ori. Ordely are numai read_customers/read_inventory/read_locations/read_orders/read_products, inclusiv după app dev clean. 58 teste/286 assertions și 6 probe HTTP PASS; config validate PASS. Detalii, comenzi și limite în [raport](../testing/08-commerce-import.md).

Preview/PHP/tunel/MySQL oprite, DB păstrată. PHP TLS configurat cu CA verificat. Tokenul local client_credentials este criptat și expiră; nu este compatibil cu perechea access/refresh cerută de autentificarea aplicației și nu se introduce ca atare în DB.

Corecția/predarea `7e87d9e3364977ad4d3886b97920ff3637e6b117` publicată, hash remote verificat; [CI Windows/Linux PASS, 36486355856](https://github.com/razvanstav/ordely/actions/runs/36486355856). Actualizarea următoare este numai documentară, pentru consemnarea CI.

> Continuă exclusiv 08. Sincronizează Git, citește STATUS, raportul 08 și D17. Pornește MySQL și preview-ul exclusiv de citire pe ordely-shop.myshopify.com; regenerează codul local și finalizează legarea autentică App Bridge. ORDELY-TEST-M08 există, ID 8239905505617: nu o recrea și nu readăuga write_orders. Importă și reimportă prin UI/worker; verifică 30 linii, 36,00 RON, date sintetice/criptare DB, aceleași ID-uri/versiuni și un singur ORDER_IMPORTED. Actualizează testele, documentele și Git înainte de închiderea 08. 09.2 rămâne PAUSED.
