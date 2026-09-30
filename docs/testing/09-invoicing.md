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

## Reorganizarea UI — 2026-09-29

Cerere explicită a utilizatorului, D19, în același modul 09. Git curat înainte de lucru, `git fetch origin` și `git pull --ff-only` PASS (Already up to date). Criteriile au fost scrise în fișă înainte de cod. Schimbate numai HTML/CSS/JS și documentația aferentă; fără migrații, dependențe sau API-uri noi.

Mediu: Windows, PHP 8.4.24 din var/tools/php-8.4.24 adăugat în PATH, MySQL 8.4.11, Node 24.19.0 din runtime-ul Codex, Chrome conectat la http://127.0.0.1:8080. Sesiune owner și magazin de dezvoltare existente. Nu s-au trimis cereri către Shopify/Oblio; s-au citit proiecțiile deja importate local.

| Comandă / scenariu executat | Rezultat |
| --- | --- |
| `php var/tools/composer.phar check` | PASS: Composer validate, 222 lint, PHPStan 8, 112 unit/768 assertions și 108 integration/664 assertions, total 220 teste |
| `php bin/http-smoke.php` | PASS: 6 verificări |
| `node --check resources/app.js` și `node --check resources/invoicing.js` | PASS, inclusiv după ajustarea finală a focusului la închiderea configurării |
| Chrome: toate cele șase pagini din meniu | PASS: o singură pagină principală vizibilă; titlu/URL/link activ corecte, Acasă 1 magazin/1 comandă/17 produse din datele locale |
| Browser Back, reload pe Facturare, Enter pe Integrări | PASS: pagină păstrată la reload, întoarcere funcțională, focus pe titlul paginii la navigare |
| Acasă → Configurează și Integrări → Shopify/Oblio | PASS: numai formularul ales vizibil; focus pe titlul configurării; Închide revine la butonul serviciului ales; credențialele nu au fost completate |
| Facturare → Ciornă nouă → calcul sintetic | PASS: net 10.00 RON + taxă 2.00 RON = 12.00 RON; editor închis fără salvare sau emitere |
| Activitate sistem: filtrul implicit și toată activitatea recentă | PASS: zero înregistrări de atenție; 100 înregistrări la schimbarea filtrului, toate detaliile tehnice închise; filtrul implicit restaurat |
| Viewport temporar 390×844: toate paginile și formularul de ciornă/Oblio | PASS: scrollWidth egal cu clientWidth, o singură pagină vizibilă, formular pe o coloană, focus pe referință la ciornă nouă; override resetat la final |
| DOM: ID-uri unice; consola Chrome | PASS: fără ID-uri duplicate, fără erori JS capturate |
| Inspecție vizuală desktop/mobil | PASS: sidebar desktop, meniu compact mobil, formulare separate, opțiuni avansate închise; pagina finală Acasă |
| UI pentru fiecare rol / schimbare între doi merchants | NOT_RUN în browser în această sesiune; condițiile au fost revizuite în cod, iar izolarea/drepturile backend rămân acoperite de suita existentă |
| Salvare ciornă din noul UI, apel extern, conectare reală Oblio, emitere | NOT_RUN în această probă vizuală; calculul local și testele HTTP nu sunt dovezi pentru aceste operații |

Composer a avertizat că nu poate crea directorul global de cache în sandbox și a continuat fără cache; verificările au ieșit cu cod 0. Capturile au fost inspectate în instrumentul de browser; nu există fișiere de captură versionate. Ultima ajustare de cod a fost întoarcerea focusului la butonul Shopify/Oblio corect, verificată în Chrome și prin sintaxa JS; CSS completează coloana pentru rolurile fără ghid de conectare. Nu s-au adăugat teste care doar reproduc markup-ul.

Publicare UI: `b1709f040b58bb403b7de960d21f4833ab8c44a7`, push PASS și `git ls-remote` confirmă hash identic. `git diff --check`, verificarea linkurilor relative din șase documente și scannerul local staged pentru secretele configurate: PASS. [CI 36575021341](https://github.com/razvanstav/ordely/actions/runs/36575021341) PASS: Windows PHP/MySQL și Linux PHP/MySQL/HTTP, urmărit cu `gh run watch 36575021341 --exit-status --interval 15`. Predarea ulterioară este numai documentară, fără cod runtime suplimentar. 09 rămâne IN_PROGRESS, proba reală Oblio NOT_RUN.

## Acces local — 2026-09-30

Cerere explicită de creare a contului personal Ordely pentru a continua integrările, D20. Git curat la început, fetch/pull --ff-only PASS (Already up to date), același branch. Mediu Windows/PHP 8.4.24/MySQL local și Chrome. Schimbările versionate sunt numai documentare; singura mutație operațională este contul/membership-ul din DB locală, fără email/parolă în raport.

| Comandă / scenariu executat | Rezultat |
| --- | --- |
| `php var/prepare-local-access.php inspect`, email furnizat prin environment temporar | PASS: cont inexistent, merchant de dezvoltare verificat și un magazin existent |
| `php var/prepare-local-access.php`, date numai prin environment temporar | PASS: creare atomică user + membership owner în merchant-ul existent, hash bcrypt recitit și verificat; valorile environment eliminate după execuție |
| Chrome: login cu contul cerut, prin formularul obișnuit | PASS: sesiune owner, spațiul/magazinul și conexiunea Shopify existente vizibile |
| Integrări → Oblio | PASS: formular deschis cu eticheta locală și câmpurile contului goale; pagina marcată pentru continuare |
| Citire autenticată Oblio / facturi / hosting nou | NOT_RUN: emailul/cheia API Oblio și o destinație de hosting nu au fost furnizate pentru acest pas |
| Suita completă PHPUnit/PHPStan | NOT_RUN din nou: fără modificări runtime; rezultatele 220 teste/CI de mai sus aparțin sesiunii anterioare |
| `git diff --check`, linkuri relative în cele șapte documente, absența helper-ului temporar | PASS |

Helper-ul temporar a folosit numai `APP_ENV=dev`, DB pe loopback și merchant-ul din contextul local existent, cu refuz dacă emailul ar fi aparținut altui context. Nu a conținut valori de credentiale și a fost eliminat după verificare. Parola explicit aleasă pentru contul local este excepția administrativă D20; nu s-a relaxat validatorul general de provisionare. API-ul și autentificarea nu s-au schimbat.

## Proba reală Oblio — 2026-09-30

Ulterior probei de acces local, utilizatorul a furnizat datele API Oblio și a confirmat emailul contului. Se folosesc criteriile 09.2b deja definite: salvare criptată, asociere magazin, autentificare și citire, fără operații fiscale. Git curat la reluare; `git fetch origin` și `git pull --ff-only` PASS, Already up to date. Mediu: Windows, PHP 8.4.24, MySQL 8.4.11 pe loopback:33060, Chrome și sesiune owner Ordely pe http://127.0.0.1:8080. Nicio schimbare runtime sau migrație nouă.

| Comandă / scenariu executat | Rezultat |
| --- | --- |
| Chrome, Integrări → Oblio → Salvează conexiunea | PASS: conexiune activă creată prin formularul existent; datele salvate nu sunt returnate în listă |
| Folosește pentru acest magazin | PASS: conexiunea asociată implicit magazinului existent, versiunea 2 |
| Citește firmele Oblio | PASS real: autentificare reușită, 1 firmă disponibilă |
| Selectarea firmei din cont → Citește seriile și TVA | PASS real: mesaj de citire reușită, 1 serie de factură și 10 intrări TVA afișate |
| `& ./var/tools/php-8.4.24/php.exe var/verify-oblio-local.php` | PASS, exit 0: o singură conexiune activă; versiune 2; credentiale decriptabile și absente în clar din envelope; asociere implicită în magazinul așteptat; audit sigur |
| Ultima comandă de închidere a formularului gol și marcarea taburilor | BLOCKED: instrumentul solicită actualizarea extensiei ChatGPT din Chrome; executarea acestei comenzi nu este confirmată. Probe reușite înaintea blocajului |
| Suita completă PHPUnit/PHPStan/HTTP | NOT_RUN din nou: fără cod runtime modificat; cele 220 teste și CI documentate mai sus sunt din implementare |
| Salvare profil fiscal, emitere/storno/PDF/email/stoc/SPV | NOT_RUN, neimplementate și în afara probei de citire |

Verificarea locală este exclusiv prin citire, limitată la `APP_ENV=dev`, DB pe loopback și merchant/store din contextul local existent. Rezultate sanitizate: `activeOblioConnections=1`, `connectionVersion=2`, `encryptedCredentialsVerified=true`, `defaultBindingForExpectedStore=true`, `safeAuditMetadataVerified=true`. Auditul conține exact `connection_created=1`, `connection_bound=1`, `invoice_configuration_read=2`; citirile au număr de rezultate 1, respectiv 12 (1 firmă + 1 serie + 10 cote). Metadata citirilor conține numai `version` și `count`, fără credentiale sau datele firmei. Helper-ul local ignorat nu conține valori de credentiale și nu apelează API-ul Oblio.

09.2b finalizat. Aceasta este o probă de cont real, distinctă de transportul simulat. Nu au fost regenerate chei, schimbate setări Oblio sau emise documente. Firma/seria test vs. producție, selecția tratamentului TVA, profilul salvat și D01/D08 pentru emitere rămân restante; 09 rămâne unicul IN_PROGRESS. Valorile reale de cont/firmă/serie și payload-urile nu sunt copiate în Git sau raport.

Predare documentară: `git diff --check` și `git diff --cached --check` PASS; verificarea PowerShell a linkurilor relative din cele șapte documente și a unicului modul activ 09 PASS. `& ./var/tools/php-8.4.24/php.exe var/check-publish-secrets.php` PASS: staged diff fără secretele configurate sau credentialele conexiunilor locale. `git check-ignore var/verify-oblio-local.php` confirmă că helper-ul rămâne local. Nu sunt rezultate CI noi pretinse pentru acest pas fără schimbări runtime.
