# Verificări 09 — ciorne locale (09.1)

2026-09-28. Windows, PHP 8.4.24, MySQL 8.4.11 nativ pe loopback:33060; DB de test separată. Date exclusiv sintetice. Migrația 007 aplicată în DB aplicației și în DB test. Nu s-a apelat Shopify/Oblio și nu s-a emis nicio factură.

## Probe și rezultate

| Comandă / scenariu | Rezultat |
| --- | --- |
| php vendor/bin/phpunit --filter InvoiceDraft | PASS după corecția concurenței: 13 teste / 88 assertions |
| php vendor/bin/phpunit --filter 'InvoiceDraft\|IdentityHttp' | PASS: 17 teste / 193 assertions, înaintea probei suplimentare a inventarului cheilor |
| php var/tools/composer.phar check | PASS final: Composer validate strict, 213 lint, PHPStan 8, 106 unit / 707 assertions, 101 integration / 634 assertions — 207 teste |
| node --check resources/invoicing.js; node --check resources/app.js | PASS final |
| php bin/http-smoke.php | PASS: 6 probe pe serverul local |
| php bin/key-status.php | PASS: structura include invoiceDraftKeyUsage; DB locală fără ciorne |
| git diff --check | PASS înaintea documentării finale |
| CI Windows/MySQL și Linux/Compose/HTTP | NOT_RUN pentru noul cod; urmează după push |
| Inspecție vizuală manuală în browser | NOT_RUN în această sesiune; sintaxa JS, servirea asset-ului și fluxul HTTP sunt verificate separat |
| Login, import comandă reală Shopify | DEFERRED_EXTERNAL prin cererea utilizatorului, nu PASS |
| Emitere/storno/PDF Oblio | NOT_RUN, în afara pasului 09.1 |

Acoperire: calcule exacte/zero/30 linii/overflow, input invalid și fără total de la client; context AEAD merchant/store/draft/revizie, tamper; roluri și granturi curente, FK composite, sesiune/CSRF/origin; creare repetată, editare cu versiune, arhivare, paginare 25+1, istoric criptat, rollback fără audit/outbox orfan. Două procese PHP concurează efectiv pentru aceeași creare și aceeași versiune de editare. CLI real inventariază cheile vechi și noi inclusiv după arhivare.

IdentityHttpTest pornește un proces PHP HTTP separat și folosește MySQL: login, magazin Manual/test, preview, create/replay, read, update/conflict, archive; alterarea tag-ului criptat produce 500 generic fără destinatar/cheie în răspuns ori log. Fixtures, chei și procesele temporare sunt curățate.

## Probleme găsite și remediate

Prima probă de creare concurentă a eșuat: după așteptarea inserării celeilalte tranzacții, citirea reviziei folosea un snapshot MySQL anterior commitului. Recitirea cu FOR SHARE folosește datele comise, la fel ca lock-ul metadatelor. Retestul concurent și suita completă au trecut. PHPStan a semnalat o aserțiune repetată considerată deja restrânsă în testul HTTP; răspunsurile sunt acum colectate înainte de aserțiuni, fără suppressions. JSON adânc invalid rămâne 400, nu 500.

UI invalidează răspunsurile întârziate la schimbarea merchant/store/filtru; modificarea formularului invalidează calculul în curs. Câmpurile sunt dezactivate în timpul salvării. Aceste protecții au fost revizuite în cod; nu sunt prezentate drept test vizual trecut.
