# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul oficial: [plan.md](plan.md).

**01–07 DONE. Modulul 08 este unicul activ, în REVIEW. 09–21 nu sunt începute.**

## Reluare pe PC-ul curent — 2026-09-28

Ultima predare de pe celălalt PC (`807e0da`) a fost adusă prin fetch + pull --ff-only: 10 commituri noi, fără conflicte sau modificări locale suprascrise. Composer install și cerințele platformei PASS; PHP 8.4.24 existent include curl. Migrațiile 005/006 sunt aplicate acum și aici, peste 001–004 păstrate.

Verificat din nou local: **190 teste PASS** (101 unit/677 assertions, 89 integration/512 assertions), 204 lint, PHPStan 8, sintaxă ambele fișiere JS și 6 verificări HTTP. MySQL al proiectului este pornit pe 33060; preview PHP standalone pe http://127.0.0.1:8080/, PID/loguri în `var/pc-resume/`.

Fișierele locale `.env` și `var/keys/keyring.json` au fost păstrate. Pe acest PC lipsesc client ID/secret Shopify și ORDELY_SHOPIFY_DEV_STORE; `var/dev-account.json` nu există. Baza de date, tokenurile și cheia de pe celălalt PC nu sunt transportate de Git. Înaintea continuării probei reale 08 trebuie configurat accesul Shopify aici și fie restaurată împreună perechea DB/keyring, fie refăcută asocierea locală pe dev store. Nu s-a pornit un tunel CLI și nu s-au executat instalări sau scrieri externe în această reluare.

## Modul 08

Implementate: import GraphQL comenzi/catalog, migrația 006, progres pe pagini în jobs, publicare atomică, deduplicare, reconciliere, bani exacți, comenzi criptate, control tenant/store, UI și procesarea locală a cererilor privacy. [Fișă](docs/modules/08-commerce-import.md), [operare](docs/commerce-import.md), [raport](docs/testing/08-commerce-import.md).

**Catalog real PASS de trei ori:** 17 produse, 26 variante, 28 poziții de stoc pe Ordely Shop; fără dubluri, toate versiunile au rămas 1. Ultima rulare, după expirarea reală a tokenului, a reînnoit automat accesul: conexiune activă versiunea 4, aceleași scopes read_orders/read_products/read_inventory/read_locations. ANATOMIK live este exclus și nu a fost folosit.

**190 teste PASS; CI Windows și Linux PASS pentru codul e8a4133.** 204 lint, PHPStan 8, 101 unit (677 assertions Windows / 678 Linux), 89 integration / 512 assertions, HTTP real pe Linux; sintaxă JS și config Shopify validate local. [CI 36420254697](https://github.com/razvanstav/ordely/actions/runs/36420254697).

## Blocaj exact

Utilizatorul a dat acordul pentru write_orders și comanda sintetică: „Hai, fa ce vrei tu. Ai acordul meu.” Comanda CLI auth a pornit, dar auto-review a blocat butonul Install al Shopify CLI Connector App: cere acord explicit și pentru datele personale afișate de Shopify (clienți: nume/email/telefon/adresă/IP/dispozitiv; proprietar: nume/email/telefon/adresă). Verificarea detaliilor UI și a documentației oficiale nu a eliminat respingerea. S-a trimis întrebarea exactă; răspunsul nu a sosit încă. Nu confunda acest blocaj cu lipsa acordului inițial pentru write_orders. Nu s-a instalat conectorul și nu s-a creat comanda. Nu ocoli refuzul prin UI, alt token sau scopes de scriere pe aplicație.

Sunt pregătite query-urile validate `docs/testing/fixtures/shopify-order-create.graphql`, `shopify-order-lookup.graphql` și `scripts/prepare-shopify-test-order.ps1` (generează var/shopify-test-order.json). Fixture: test=true, PENDING, 30 linii, date inventate, fără mesaje, plăți sau modificări de stoc. Pasul următor: rezolvă confirmarea exactă a instalării CLI doar pe ordely-shop.myshopify.com, verifică să nu existe ORDELY-TEST-M08, apoi creează comanda și verifică import/reimport, bani/linii/PCD. Nu începe 09.

Sesiunea CLI auth a expirat așteptând callback-ul OAuth. După confirmare, rulează din nou auth și folosește noua pagină de instalare; pagina păstrată arată permisiunile, dar callback-ul vechi nu mai este activ.

## Mediu din predarea celuilalt PC și Git

PHP 8.4.24 în var/tools/php-8.4.24, Composer 2.10.3, MySQL 8.4.11 pe 33060, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. Migrații 001–006 aplicate în app/test. Păstrează var/keys/keyring.json împreună cu DB. `.env`, tokenurile și contul sintetic sunt ignorate.

La predarea de pe celălalt PC, MySQL și preview CLI rămăseseră pornite, iar contul sintetic era în var/dev-account.json. Acestea nu dovedesc starea PC-ului curent, descrisă separat mai sus. Workerul este CLI: php bin/worker.php 150; nu există supervisor sau scheduler de producție. Tunelul HTTPS se schimbă la restart.

Remote https://github.com/razvanstav/ordely.git, branch codex/modul-01-arhitectura. Codul e8a4133 este publicat, hash-ul remote verificat, CI Windows/Linux PASS; această predare este o actualizare numai de documentație. La reluare citește AGENTS, fișa 08, raportul și D14. Retenția pentru date reale, distribuția și aprobările de producție rămân înainte de pilot.
