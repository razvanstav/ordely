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
| git diff --check și legături locale în predare | PASS |
| CI Windows/MySQL și Linux/Compose/HTTP | PASS ambele, cod f622aab, [rularea 36480973254](https://github.com/razvanstav/ordely/actions/runs/36480973254) |
| Inspecție vizuală manuală în browser | NOT_RUN în această sesiune; sintaxa JS, servirea asset-ului și fluxul HTTP sunt verificate separat |
| Login, import comandă reală Shopify | DEFERRED_EXTERNAL prin cererea utilizatorului, nu PASS |
| Emitere/storno/PDF Oblio | NOT_RUN, în afara pasului 09.1 |

Acoperire: calcule exacte/zero/30 linii/overflow, input invalid și fără total de la client; context AEAD merchant/store/draft/revizie, tamper; roluri și granturi curente, FK composite, sesiune/CSRF/origin; creare repetată, editare cu versiune, arhivare, paginare 25+1, istoric criptat, rollback fără audit/outbox orfan. Două procese PHP concurează efectiv pentru aceeași creare și aceeași versiune de editare. CLI real inventariază cheile vechi și noi inclusiv după arhivare.

IdentityHttpTest pornește un proces PHP HTTP separat și folosește MySQL: login, magazin Manual/test, preview, create/replay, read, update/conflict, archive; alterarea tag-ului criptat produce 500 generic fără destinatar/cheie în răspuns ori log. Fixtures, chei și procesele temporare sunt curățate.

## Probleme găsite și remediate

Prima probă de creare concurentă a eșuat: după așteptarea inserării celeilalte tranzacții, citirea reviziei folosea un snapshot MySQL anterior commitului. Recitirea cu FOR SHARE folosește datele comise, la fel ca lock-ul metadatelor. Retestul concurent și suita completă au trecut. PHPStan a semnalat o aserțiune repetată considerată deja restrânsă în testul HTTP; răspunsurile sunt acum colectate înainte de aserțiuni, fără suppressions. JSON adânc invalid rămâne 400, nu 500.

UI invalidează răspunsurile întârziate la schimbarea merchant/store/filtru; modificarea formularului invalidează calculul în curs. Câmpurile sunt dezactivate în timpul salvării. Aceste protecții au fost revizuite în cod; nu sunt prezentate drept test vizual trecut.

Codul f622aab39b8ff4791bb4c6cf300471b860ceb4c6 este publicat și hash-ul remote a fost verificat identic. Predarea ulterioară CI schimbă numai documentația; dovada runtime se referă la acest cod. 09.1 este închis, 09 rămâne IN_PROGRESS pentru 09.2/09.3.

## Pregătire 09.2 — 2026-09-29

Schimbare numai de documentație, înaintea implementării. Git: git status --short --branch, branch și remote verificate; git fetch origin și git pull --ff-only PASS, Already up to date. Inspecție manuală: fișa/ghidul 09, D01/D08/D16, brief, arhitectură, Core InvoiceDraft/CustomerSnapshot/CommercialLine și DraftDocument.

Documentația oficială https://www.oblio.eu/api și exemplele oficiale https://github.com/OblioSoftware/OblioApi citite cu web open/find: PASS documentar; nu reprezintă probă de cont. Planul, lipsurile datelor și criteriile sunt în ../oblio-integration.md. D01/D08: răspunsuri utilizator în așteptare.

Runtime nou, MySQL, UI 09.2, autentificare/read-only/emitere/storno/PDF Oblio: NOT_RUN, neimplementate în această etapă. Cele 207 teste PASS ale codului actual rămân probe anterioare, nu sunt declarate rerulate pentru documentație.

Verificări documentare: git diff --check PASS; verificare PowerShell a celor 7 documente și a linkurilor relative PASS; tabelul planului confirmă un singur modul activ, 09. Nicio verificare runtime suplimentară necesară acestui pas documentar.

## Implementare locală 09.2b — 2026-09-29

Mediu: Windows, PHP 8.4.24, MySQL 8.4.11 app/test separate, migrații existente 001–007. Node 24.19.0 din runtime-ul local. Nicio migrație nouă. Toate fixture-urile de transport/DB sunt sintetice.

| Comandă/scenariu efectiv | Rezultat |
| --- | --- |
| php var/tools/composer.phar check, prima rulare | FAIL într-o aserțiune nouă care presupunea ordinea rândurilor de audit fără ORDER BY; corectată să verifice exact o înregistrare a acțiunii, independent de ordine |
| php var/tools/composer.phar check, rularea finală după corecție | PASS integral: validate strict, 222 lint, PHPStan 8, 112 unit/768 assertions și 108 integration/664 assertions |
| php bin/lint.php (prin composer check) | PASS: 222 fișiere |
| php vendor/phpstan/phpstan/phpstan analyse --no-progress --memory-limit=512M | PASS: PHPStan 8, rerulat după corecția testului |
| php vendor/bin/phpunit --testsuite Unit (prin composer check) | PASS: 112 teste / 768 assertions |
| php vendor/bin/phpunit --testsuite Integration, după corecție | PASS: 108 teste / 664 assertions |
| node --check resources/app.js (Node 24.19.0) | PASS |
| php bin/http-smoke.php | PASS: 6 probe pe serverul local |
| Chrome, login Ordely și formular conexiune | PASS: email/parolă locale, încărcare interfață, provider Oblio și câmpuri email/cheie, inspectare vizuală |
| Transfer automat al credentialelor din tabul Oblio | BLOCKED: accesul DOM furnizează valori mascate; validarea emailului a împiedicat trimiterea formularului. Câmpuri golite; salvare directă cerută utilizatorului |
| Autentificare și citire firme/serii/TVA din contul real | NOT_RUN până la salvarea credentialelor în UI |
| Salvarea profilului fiscal, emitere/storno/PDF/email/stoc/SPV | NOT_RUN, în afara pasului și neimplementate |

Acoperire nouă: validarea email/cheie înainte de persistare, form-urlencoded auth, bearer separat, companie verificată înainte de nomenclatoare, filtrare serii de factură, conservarea lexicală a TVA inclusiv 7.1250, JSON/token/date invalide, 401/403/429/5xx/redirect, lipsa credentialelor din răspuns/audit/DB în clar, toate metodele documentelor Unsupported fără rețea. Integrare pe MySQL: API/sesiune/CSRF/origin/versiune/roluri/store/tenant, revocare conexiune/rotație/disable membership între începutul și sfârșitul apelului, audit numai după revalidare, erori generice și Retry-After. Schimbările în timpul transportului sunt injectate controlat în aceste teste; nu sunt prezentate ca probe concurente noi cu două procese.

În prima rulare țintită, fixture-ul folosea status membership inexistent (`revoked`); corectat la `disabled` conform schemei. Primele două erori PHPStan erau adnotări @return pe aceeași linie cu @param; separate, fără suppressions. Nu există defecte de test restante. CI pentru codul nou se consemnează după publicare; probele CI vechi nu validează acest adaptor.

git diff --check și verificarea PowerShell a linkurilor relative din cele șapte documente de predare: PASS. Planul păstrează numai 09 IN_PROGRESS. Contul sintetic de dezvoltare, .env, keyring și eventualele credentiale locale nu sunt incluse în Git.

Publicare finală: `11dddf49ebe76f2720df52914df34699ca12c927`, push PASS și git ls-remote confirmă hash identic. Scannerul local al staged diff a verificat absența secretelor configurate, parolei dev și credentialelor decryptate din conexiunile locale: PASS. [CI 36551856411](https://github.com/razvanstav/ordely/actions/runs/36551856411) PASS Windows PHP/MySQL și Linux PHP/MySQL/HTTP; gh run watch --exit-status folosit pentru urmărire. Actualizarea ulterioară schimbă numai documentele de predare.
