# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul oficial: [plan.md](plan.md).

**01–07 DONE. Modulul 08 este unicul activ, în REVIEW. 09–21 nu sunt începute.**

## Modul 08

Implementate: import GraphQL comenzi/catalog, migrația 006, progres pe pagini în jobs, publicare atomică, deduplicare, reconciliere, bani exacți, comenzi criptate, control tenant/store, UI și procesarea locală a cererilor privacy. [Fișă](docs/modules/08-commerce-import.md), [operare](docs/commerce-import.md), [raport](docs/testing/08-commerce-import.md).

**Catalog real PASS de două ori:** 17 produse, 26 variante, 28 poziții de stoc pe Ordely Shop; fără dubluri, toate versiunile au rămas 1. Aplicația are numai read_orders/read_products/read_inventory/read_locations. Refresh real a confirmat scopes și conexiunea activă la versiunea 3. ANATOMIK live este exclus și nu a fost folosit.

**190 teste PASS; CI Windows și Linux PASS pentru codul e8a4133.** 204 lint, PHPStan 8, 101 unit (677 assertions Windows / 678 Linux), 89 integration / 512 assertions, HTTP real pe Linux; sintaxă JS și config Shopify validate local. [CI 36420254697](https://github.com/razvanstav/ordely/actions/runs/36420254697).

## Blocaj exact

Ordely Shop nu are comenzi. Proba reală a unei comenzi cu 30 de linii și date client sintetice rămâne neexecutată. Auto-review a respins acordarea write_orders către Shopify CLI, deoarece accesul de scriere nu fusese autorizat explicit de utilizator. Nu s-a acordat acel acces și nu s-a creat comanda. Nu ocoli refuzul prin UI, alt token sau scopes de scriere pe aplicație.

Sunt pregătite `docs/testing/fixtures/shopify-order-create.graphql` (validată) și `scripts/prepare-shopify-test-order.ps1` (generează var/shopify-test-order.json). Fixture: test=true, PENDING, 30 linii, date inventate, fără mesaje, plăți sau modificări de stoc. Pasul următor: acord explicit pentru write_orders CLI doar pe ordely-shop.myshopify.com și crearea acestei comenzi, apoi import/reimport, verificarea banilor/liniilor/PCD și închiderea 08. Nu începe 09.

## Mediu și Git

PHP 8.4.24 în var/tools/php-8.4.24, Composer 2.10.3, MySQL 8.4.11 pe 33060, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. Migrații 001–006 aplicate în app/test. Păstrează var/keys/keyring.json împreună cu DB. `.env`, tokenurile și contul sintetic sunt ignorate.

MySQL și preview CLI rămân pornite; panou local http://127.0.0.1:8080/. Workerul este CLI: php bin/worker.php 150; nu există supervisor sau scheduler de producție. Tunelul HTTPS se schimbă la restart. Contul local sintetic este în var/dev-account.json, fără afișarea secretelor.

Remote https://github.com/razvanstav/ordely.git, branch codex/modul-01-arhitectura. Codul e8a4133 este publicat, hash-ul remote verificat, CI Windows/Linux PASS; această predare este o actualizare numai de documentație. La reluare citește AGENTS, fișa 08, raportul și D14. Retenția pentru date reale, distribuția și aprobările de producție rămân înainte de pilot.
