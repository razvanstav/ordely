# Verificări modul 08 — 2026-09-28

Stare curentă: REVIEW, 2026-09-29. Fixture-ul real există, ID 8239905505617: 30 linii/36,00 RON, creat unic prin client_credentials. Query-urile originale, paginarea 25+5, datele sintetice, normalizarea și OrderCipher au trecut de două ori. Corectat read_customers lipsă; write_orders retras și verificat. Importul/reimportul persistat prin App Bridge/worker rămâne NOT_RUN. Preview/PHP/tunel/MySQL oprite, datele păstrate. Vezi ultima secțiune pentru predarea curentă; secțiunile anterioare sunt istorice.

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

## Procedură istorică — înlocuită de proba offline de la final

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

## Reluare reală pe PC-ul curent — 2026-09-29

Acorduri explicite primite în această sesiune:

- „Da, autorizez explicit pe Ordely Shop dev”: CLI Connector App, write_orders și datele clienților (nume/email/telefon/adresă/IP/dispozitiv) și proprietarului (nume/email/telefon/adresă), până la revocare.
- „Am confirmat asocierea CLI”: loginul contului pe acest PC.
- „Da, autorizez preview-ul și accesul temporar Ordely”: actualizarea preview-ului Ordely, write_orders temporar pe aplicație și crearea aceleiași fixture prin token offline, apoi retragerea scrierii și import/reimport. Nu este necesar un nou acord pentru aceiași pași.

| Probă executată | Rezultat și limită |
| --- | --- |
| npm install --prefix var/shopify-cli --no-audit --no-fund @shopify/cli@4.8.2; shopify version | PASS, instalare locală ignorată de Git |
| shopify store auth list | Fără sesiuni inițiale pe acest PC |
| shopify app config validate --json după login | PASS, valid=true, issues=[] |
| shopify store auth --store ordely-shop.myshopify.com --scopes write_orders --no-color | PASS, după acordul explicit |
| shopify app env pull --client-id 62da15c72a85d17d1ee0cf8d7fa5058d --env-file .env --no-color | PASS, ieșirea cu secrete suprimată, .env existent păstrat; allowlist Ordely dev adăugat |
| Lookup fixture prin CLI, API 2026-07 | PASS de două ori: domeniu exact, RON, zero rezultate |
| orderCreate prin CLI, input 30 linii/test/PENDING/BYPASS/notificări false | FAIL API ACCESS_DENIED: cere offline token. Nicio comandă creată, confirmat prin lookup ulterior |
| shopify app dev --store ordely-shop.myshopify.com --client-id 62da15c72a85d17d1ee0cf8d7fa5058d --skip-dependencies-installation --no-color | PASS după al doilea acord; preview Ready, URL HTTPS, read_inventory/read_locations/read_orders/read_products/write_orders auto-granted |
| Pagina embedded locală și /ready | PASS HTTP 200 |
| php vendor/bin/phpunit --filter 'Shopify\|CommerceImport' | PASS: 58 teste / 286 assertions |
| php bin/http-smoke.php | PASS: 6 probe |
| Legare App Bridge și token offline în DB locală | ÎN AȘTEPTARE: connection=null la ultima verificare |
| Creare offline, retragere write_orders, import/reimport 30 linii | NOT_RUN; depind de legarea autentică |

Validatorul GraphQL confirmă lookup și orderCreate (revizia 2). Lista sa de scope-uri include alternative cumulativ; [Order](https://shopify.dev/docs/api/admin-graphql/latest/objects/Order) cere read_orders SAU read_marketplace_orders SAU read_quick_sale. Nu am cerut scope-urile alternative; [orderCreate](https://shopify.dev/docs/api/admin-graphql/latest/mutations/orderCreate) cere write_orders și offline token. [CLI store auth](https://shopify.dev/docs/api/shopify-cli/store/store-auth) păstrează un token online. Procedura CLI de creare de mai sus este depășită și nu trebuie repetată.

Browser automation nu pornește pe acest PC: ambele instrumente au returnat `windows sandbox failed: helper_unknown_error: apply deny-read ACLs`. Nu s-au schimbat setări de securitate. Pagina de preview a fost deschisă prin tasta p a CLI; codul local este în var/shopify-link-code.txt (TTL 10 minute). Utilizatorul trebuie să îl introducă în aplicația Ordely din Shopify și să apese Conectează magazinul; apoi agentul verifică DB, fără presupunerea unei conectări reușite doar pe baza UI.

Helper-ele locale ignorate de Git: `php var/prepare-shopify-local.php` păstrează contul sintetic și regenerează codul; `php var/shopify-live-check.php status` verifică legătura. Helper-ul fixture este pregătit numai pentru dev allowlist, lookup înainte de creare, blocare locală și marker înainte de mutation; un rezultat incert interzice repetarea automată. Nu s-a executat încă modul fixture. Secretele nu sunt expuse în rezultate/documente.

La acel moment, configurația locală shopify.app.toml conținea temporar write_orders, nepublicat în Git. Acesta a fost retras ulterior, conform probelor de mai jos. La mutare pe alt PC, codul și notele vin din Git, iar setup-ul local se recreează conform docs/shopify.md; tokenurile și helper-ele var nu sunt transferate.

## Continuare autonomă și predare curentă — 2026-09-29

Utilizatorul a autorizat toate automatizările și accesul necesar pe Ordely dev, cerând închiderea mediului dacă un pas nu poate fi continuat fără el. Nu există acord restant pentru aceste probe. Nu s-a lucrat pe ANATOMIK live.

Browser automation rămâne BLOCKED tehnic (sandbox ACL/kernel). Alternativa oficială [client credentials pentru magazinele aceleiași organizații](https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant) a funcționat cu aplicația Ordely deja instalată pe Ordely dev. Tokenul real a fost păstrat criptat într-un fișier ignorat, cu expirare aproximativ 24h. Acesta nu este un ID token App Bridge și nu a fost introdus artificial în shopify_links/provider_connections. Fluxul de autentificare al aplicației nu a fost modificat.

| Probă executată | Rezultat și limită |
| --- | --- |
| `php var/shopify-own-store-probe.php`, prima încercare | FAIL transport cURL 60: lipsește CA în PHP; nu este refuz Shopify |
| Descărcare HTTPS Mozilla CA de la curl, verificare SHA-256, configurare curl.cainfo/openssl.cafile în php.ini | PASS; fără dezactivarea verificării certificatului/hostului. Backup local și bundle în var; procedură în setup |
| `php var/shopify-own-store-probe.php` după CA | PASS HTTP 200, domeniu Ordely dev verificat cu HttpShopifyGateway; client_credentials, expires_in=86399 |
| `php var/shopify-own-store-test.php fixture` | PASS: lookup zero înainte, marker local înainte de mutation; creată o singură ORDELY-TEST-M08, ID `gid://shopify/Order/8239905505617`, test=true, PENDING, 30 linii, total 36,00 RON; inventory BYPASS, notificări false, fără transactions |
| Retragere write_orders din TOML, restart preview și token nou; `php var/shopify-own-store-test.php scopes` | PASS: API confirmă exclusiv scope-uri de citire. Watcher-ul nu aplicase singur schimbarea; restartul a aplicat-o |
| Query original resources/shopify/order.graphql cu patru scope-uri | FAIL real ACCESS_DENIED pe order.customer: cere read_customers. Defect de configurație identificat, apoi remediat |
| Adăugare read_customers, `shopify app config validate --json`, restart preview, token nou și scopes | PASS: valid=true, issues=[]; read_customers/read_inventory/read_locations/read_orders/read_products, fără write_orders |
| `php var/shopify-own-store-test.php read`, de două ori după corecție | PASS: indexul original și order.graphql nemodificate, pagini 25+5, 30 ID-uri de linie distincte, ImportNormalizer real, email/adresă sintetice exacte, OrderCipher seal/open real, fără text personal în envelope; hash normalizat identic la recitire |
| Sume normalizate | PASS: original/curent 3600 bani RON, taxă sintetică 570, încasat 0, restant 3600; rata din fixture este exclusiv dată sintetică |
| `php vendor/bin/phpunit --filter 'Shopify\|CommerceImport'`, rerulat după corecție | PASS: 58 teste / 286 assertions |
| `php bin/http-smoke.php`, rerulat după corecție | PASS: 6 verificări |
| `php var/shopify-live-check.php status` | connection=null: App Bridge local încă NOT_RUN; nu se pretinde import/reimport persistat din probele directe |
| Ieșire CLI, `shopify app dev clean --store ordely-shop.myshopify.com --client-id 62da15c72a85d17d1ee0cf8d7fa5058d --no-color` | PASS: preview oprit, versiunea activă restaurată; procesele CLI/PHP/tunel închise |
| Token nou și scopes după app dev clean | PASS: aceleași cinci scope-uri de citire; write_orders absent și după cleanup |
| `./scripts/windows-mysql.ps1 -Action Stop` | PASS: MySQL local oprit, date păstrate în var/mysql/data |

Hash-ul normalizat al comenzii la cele două citiri: `29ee735f7ceb4a1cf497cc353f6b525a3538afa68cbed0d56a1fbe6f865c1dc1`. Citirile live confirmă accesul la câmpurile sintetice în dev; nu reprezintă aprobare Shopify pentru datele de producție. Criptarea este probată cu OrderCipher pe documentul real normalizat, dar persistarea în commerce_records, staging, evenimentele și stabilitatea ID-urilor interne rămân de verificat prin fluxul complet.

Helper-ul local a avut și două erori de pregătire, remediate înaintea probei finale: checksum-ul descărcat era returnat ca bytes de PowerShell (citit ulterior din fișier), iar documentul mare fusese trimis direct în Secrets (înlocuit cu OrderCipher, componenta corectă). Acestea nu au produs comenzi suplimentare. Marker-ul de creare este păstrat; nu se repetă mutation.

La reluare pe acest PC: pornește MySQL și preview-ul cu TOML actual, rulează `php var/prepare-shopify-local.php` pentru cod nou și finalizează legarea autentică App Bridge. `php var/shopify-live-check.php status` trebuie să confirme conexiunea. Folosește fixture-ul existent, apoi `start`, `php bin/worker.php 150 <merchantId>` și `inspect` din helper sau UI-ul documentat; repetă importul complet și compară numărători/ID-uri/versiuni/ORDER_IMPORTED/staging/criptare DB. Helper-ele var sunt locale, neversionate; pe alt PC folosește procedura UI/worker din [operare](../commerce-import.md). Nu folosi modul fixture și nu readăuga write_orders. Nu accepta tokenul client_credentials ca pereche access/refresh și nu simula App Bridge pentru a închide testul.

Surse: [Customer cere read_customers](https://shopify.dev/docs/api/admin-graphql/latest/objects/Customer), [grant oficial pentru propriile magazine](https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant), [Mozilla CA distribuit de curl](https://curl.se/docs/caextract.html). Proba live, nu doar validarea schemei GraphQL, a demonstrat necesitatea scope-ului lipsă.

Publicare: commit `7e87d9e3364977ad4d3886b97920ff3637e6b117`, push reușit și hash origin verificat identic. [CI 36486355856](https://github.com/razvanstav/ordely/actions/runs/36486355856) completed/success: Windows PHP + native MySQL și Linux PHP + MySQL + HTTP, ambele PASS. Actualizarea ulterioară schimbă doar documentele pentru a salva acest rezultat; fără probe runtime noi și fără declararea importului persistat ca trecut.
