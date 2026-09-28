# Verificări modul 08 — 2026-09-28

Stare: REVIEW. Codul și catalogul real sunt verificate; testul comenzii reale sintetice este BLOCKED, deci modulul nu este DONE.

## Reluare verificată pe PC-ul curent

2026-09-28, cod/predare `807e0da` după 10 commituri preluate fără conflicte. `composer install` și `check-platform-reqs` PASS; PHP 8.4.24 cu curl și MySQL 8.4.11. `php bin/migrate.php` aplică 005/006; schema de test este actualizată de suită. `composer check` PASS: 204 lint, PHPStan 8, 101 unit/677 assertions, 89 integration/512 assertions. `node --check resources/app.js` și `resources/shopify.js` PASS; `php bin/http-smoke.php` PASS, 6 verificări, preview standalone 8080.

Nu s-au rerulat probele Shopify reale pe acest PC: client ID/secret și dev allowlist lipsesc local, iar DB/tokenurile celuilalt PC nu au fost transferate. `.env` și keyring-ul existente aici au fost păstrate. Rezultatele reale de mai jos sunt dovezile sesiunii de pe celălalt PC, nu rezultate pretinse ale sincronizării curente.

## Automat

Mediu: Windows, PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–006 în app/test; Node 24.19.0 și Shopify CLI 4.8.2.

| Comandă/scenariu executat | Rezultat | Dovadă și limite |
| --- | --- | --- |
| `php var/tools/composer.phar check` cu PHP 8.4 în PATH | PASS | composer validate, 204 PHP lint, PHPStan level 8, 101 unit / 677 assertions, 89 integration / 510 assertions; total 190 teste |
| `php vendor/phpunit/phpunit/phpunit --filter 'CommerceImportTest\|ShopifyConcurrencyTest'` după ultimul guard înainte de commit | PASS | 17 teste / 145 assertions |
| `php vendor/phpstan/phpstan/phpstan analyse --no-progress --memory-limit=512M` după ultimul guard | PASS | fără erori |
| `php vendor/phpunit/phpunit/phpunit --filter 'CommerceImportTest\|ShopifyHttpTest'` după etichetele produselor în listele de variante/stoc | PASS | 18 teste / 153 assertions; PHPStan și node --check rerulate, PASS |
| `node --check resources/app.js`, `node --check resources/shopify.js`, `git diff --check` | PASS | sintaxă JS și whitespace |
| `php bin/migrate.php`, `php bin/migrate.php --test` | PASS | 006 aplicată în app și test |
| `shopify app config validate --json` | PASS | valid=true, issues=[] |
| Shopify search_docs și validate pentru resources/shopify/*.graphql | PASS | inventory/orders/products/variants rev.1; order rev.2; artefacte ordely-08-* |
| Mutation fixture `docs/testing/fixtures/shopify-order-create.graphql` | PASS (validare) | artefact ordely-08-test-order rev.1; **NU executată** |
| `./scripts/prepare-shopify-test-order.ps1` | PASS | pregătește JSON local: 30 linii sintetice, test=true, PENDING, inventory BYPASS, ambele notificări false |
| [CI 36420254697](https://github.com/razvanstav/ordely/actions/runs/36420254697), cod `e8a4133` | PASS | Windows PHP + native MySQL: 101 unit / 677 assertions, 89 integration / 512 assertions; Linux PHP + MySQL + HTTP: 101 unit / 678 assertions, 89 integration / 512 assertions. Ambele: 204 lint, PHPStan 8 și suita completă; Linux: verificări HTTP reale |

Commitul de cod `e8a4133c608dfcdfae9007ceec57e250180e194f` este publicat pe branch-ul `codex/modul-01-arhitectura`; hash-ul remote a fost verificat identic. Actualizarea de predare după CI modifică numai documente, fără teste runtime noi.

Testele modulului acoperă: decimal exact și precizie invalidă, monedă necunoscută, taxe/reduceri/rambursări păstrate distinct, adrese/variante lipsă, stoc negativ/neurmarit, criptare document mare și protecție tenant/store/ordine bucăți, paginare și publicare atomică, import repetat fără evenimente duble, reconcilieri, watermark/retry, expirarea lease-ului, conexiune revocată în timpul citirii, restart cu job vechi în zbor, izolare tenant/store, CSRF/sesiune/HTTP, rol viewer, 429/THROTTLED/ACCESS_DENIED fără răspuns parțial, export privacy fără confirmare falsă a livrării, redact/reimport, protecție reinstalare, schimbarea comenzii între pagini, expirarea staging-ului abandonat.

Concurența este probată cu două procese PHP și DB comisă: două start-uri returnează același run ID, doi workeri publică o singură comandă și un singur ORDER_IMPORTED. Providerul în această probă este sintetic; accesul Shopify real este verificat separat mai jos.

## Shopify real — numai Ordely Shop

Scopes după refresh real: `read_inventory,read_locations,read_orders,read_products`; tokenurile rămân criptate. Conexiunea activă din 07 a trecut la versiunea 3, apoi la 4 prin refresh automat al tokenului expirat în a treia rulare. Aplicația nu are permisiuni de scriere.

| Scenariu | Rezultat | Dovadă |
| --- | --- | --- |
| Start import din UI, `php bin/worker.php 150` | PASS | run 3e0d5fa6466dc693e6d06d4bd92a27e1, completed 11:56:20 UTC; 17 produse, 26 variante, 28 inventare |
| Repetare din UI și worker | PASS | run a9c7433f93ee5d40f7cac0936cd4ddf8, completed 12:02:53 UTC; aceleași numărători, toate versiunile 1 |
| Import după expirarea reală a access tokenului | PASS | run 3160f3902e895354c82b762d0b59c0ac, completed 13:32:18 UTC; worker 45 jobs, refresh automat versiune 3→4, 17/26/28 înregistrări și versiunile 1; orders încă gol |
| Citire inventar API | PASS | 28 inventare provenite din cele 26 variante; nu presupune că o singură variantă a necesitat >25 locații |
| UI variante, pagina 1 și 2 | PASS | 25 variante apoi ultima variantă; fără repetare |
| Comparație cu Shopify Admin | PASS | Products pe ordely-shop arată „Select all 17 on page”; lista Ordely conține 17 produse |
| Dovadă UI | PASS | Captură locală var/module-08-catalog.png, ignorată de Git |
| Query orders pe magazinul dev | PASS, gol | Magazinul nu are comenzi; **nu validează încă importul unei comenzi** |
| Import order cu date client, linii >25, total comparat cu Shopify | BLOCKED | E necesară o comandă sintetică; pregătită dar necreată |
| Acordare `write_orders` către Shopify CLI pentru fixture | BLOCKED la Install | Acordul utilizatorului pentru write_orders/comanda de test a fost primit. `shopify store auth --store ordely-shop.myshopify.com --scopes write_orders --no-color` a deschis instalarea. Auto-review a respins Install pentru lipsa confirmării explicite a datelor personale afișate; verificarea detaliilor și o reîncercare pe aceeași cale nu au schimbat refuzul |
| Query verificare fixture existentă | PASS (validare), NOT_RUN pe CLI | `docs/testing/fixtures/shopify-order-lookup.graphql`, artefact ordely-08-fixture-lookup rev.1; trebuie folosit înainte de orice creare/retry după auth |
| PCD / distribuție producție | NOT_RUN | Nu s-au solicitat și nu se pretind aprobări de producție; câmpurile personale ale unei comenzi dev urmează proba reală |

## Reluare exactă

Utilizatorul a aprobat write_orders și comanda sintetică prin „Hai, fa ce vrei tu. Ai acordul meu.” Blocajul curent este confirmarea cerută de auto-review pentru instalarea **Shopify CLI Connector App numai pe Ordely Shop dev**, cu editarea comenzilor și acces la datele clienților (nume, email, telefon, adresă, IP/dispozitiv) și proprietarului (nume, email, telefon, adresă), până la revocare. Întrebarea exactă a fost trimisă, fără răspuns încă. Captură: var/module-08-cli-permissions.png, ignorată de Git. Nu ocoli respingerea prin Admin UI, alt token sau modificarea scopes aplicației.

Detaliile UI afișează numai „Edit orders — All order history for the last 60 days”. Shopify explică faptul că accesul la comenzi include PII client, iar aplicațiile instalate au datele proprietarului: [permisiuni și PII](https://help.shopify.com/en/manual/apps/finding-choosing-apps), [write include read](https://shopify.dev/docs/apps/build/authentication-authorization/manage-access-scopes). Nu au fost cerute scopes read_customers sau staff; aceste dovezi nu au eliminat cerința suplimentară a auto-review. Nu instala până la rezolvarea confirmării.

Comenzi de reluare, numai după confirmarea exactă (prefixează intern fiecare comandă Shopify cu variabilele agentului conform skill-ului CLI):

Prima sesiune auth s-a încheiat cu `Timed out waiting for OAuth callback`. Repornește auth după confirmare și folosește pagina nou deschisă; nu încerca finalizarea prin vechiul callback expirat.

```powershell
shopify store auth --store ordely-shop.myshopify.com --scopes write_orders --no-color
shopify store execute --store ordely-shop.myshopify.com --version 2026-07 --query-file docs/testing/fixtures/shopify-order-lookup.graphql --output-file var/shopify-test-order-lookup.json
```

Inspectează lookup-ul: domeniul trebuie să fie ordely-shop.myshopify.com; zero comenzi înainte de crearea unică. Dacă fixture există, nu crea din nou. Numai în cazul zero:

```powershell
./scripts/prepare-shopify-test-order.ps1
shopify store execute --store ordely-shop.myshopify.com --version 2026-07 --query-file docs/testing/fixtures/shopify-order-create.graphql --variable-file var/shopify-test-order.json --allow-mutations --output-file var/shopify-test-order-result.json
```

După acord: autentificare CLI pe dev store, verifică întâi dacă tag-ul/numele `ORDELY-TEST-M08` există (pentru a nu duplica după timeout), apoi execută mutation validată cu fișierul `var/shopify-test-order.json`, `--allow-mutations` și API 2026-07. Dacă execuția este ambiguă, caută comanda înainte de retry. Fără plăți, mesaje către clienți sau schimbări de stoc. Import/reimport din aplicație, verifică 30 linii, bani și criptare; confirmă/repară PCD dev dacă Shopify refuză câmpurile. Finalizează raportul, CI și criteriile 08 înainte de 09.
