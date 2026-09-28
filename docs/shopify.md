# Shopify — instalare și operare în dezvoltare

Modul 07, PHP 8.4 + MySQL, API `2026-07`. Aplicația Ordely este testată numai pe **ordely-shop.myshopify.com (dev)**. ANATOMIK live este exclus explicit. Configurația din `shopify.app.toml` identifică aplicația de dezvoltare; `https://example.com` este un placeholder, nu un deployment.

## Pornire pe acest PC

1. Pornește MySQL cu `./scripts/windows-mysql.ps1 -Action Start` și aplică migrațiile cu `php bin/migrate.php`.
2. Creează keyring numai dacă lipsește: `php bin/keyring.php`. Păstrează cheile existente; înlocuirea lor face tokenurile din DB imposibil de decriptat.
3. Folosește Shopify CLI 4.8.2 și Node >=22.12 (verificat cu 24.19.0). `shopify app config link` leagă aplicația existentă. `shopify app env pull` salvează client ID/secret în `.env`, care este ignorat. Nu copia ieșirea cu secrete în chat, documente sau Git.
4. Setează `ORDELY_SHOPIFY_DEV_STORE=ordely-shop.myshopify.com` în `.env`. Backendul respinge alte domenii în dev/test. PHP trebuie să aibă `curl` și un CA bundle valid; nu dezactiva verificarea TLS. PHP portabil Windows de pe acest PC folosește `var/tools/cacert.pem` în `curl.cainfo` și `openssl.cafile`.
5. `shopify app config validate --json`, apoi `shopify app dev --store ordely-shop.myshopify.com --skip-dependencies-installation`. PHP trebuie să fie în PATH. Nu folosi `--no-update`: acea opțiune păstrează URL-ul placeholder. Tunelul HTTPS automat permite webhookuri reale. `--use-localhost` nu le permite.
6. CLI pornește `php bin/serve-shopify.php`, cu port primit din mediu. `shopify.web.toml` preferă 8080. Deschide preview-ul Shopify indicat de CLI și panoul Ordely local. Procesele PHP/tunel/MySQL trebuie să rămână pornite pe acest PC. URL-ul tunelului se poate schimba la repornire.

`var/dev-account.json` conține contul sintetic creat pe acest PC pentru probă; nu este parte din distribuție sau Git. Conturile obișnuite se creează prin provisionarea existentă. Nu muta acest fișier în `public`.

## Legare și tokenuri

Owner/admin Ordely cu acces la toate magazinele selectează un store Shopify și domeniul canonic, apoi generează un cod valabil 10 minute. Codul are 256 biți aleatori; DB păstrează numai hash-ul. Introducerea lui în aplicația embedded cere simultan ID token App Bridge valid. Drepturile Ordely sunt recitite la consumare. Nu se folosesc cookies third-party și nu se acceptă un simplu `shop` din query ca autentificare.

`shopify_links` rezervă unic domeniul și perechea merchant/store. Asocierea nu poate fi preluată de alt tenant, nici după revocare; transferul între tenants cere un viitor flux explicit. Un cod consumat returnează aceeași conexiune la retry; un cod nou permite reautorizare/reinstalare și revocă vechea conexiune. Bind/unbind generic nu poate muta o conexiune Shopify.

Token exchange cere `expiring=1`; access și refresh token, scopes și expirări sunt criptate cu AES-GCM și context merchant/connection/provider. Pe dev store s-au confirmat access TTL 3600 secunde și refresh TTL 7776000 secunde; codul folosește valorile din răspuns, nu aceste constante. Refresh la mai puțin de 60 secunde de expirare; rândul de instalare serializează apelurile concurente. Perechea nouă se păstrează inclusiv dacă următorul apel API eșuează temporar. La 401/403 sau refresh expirat, revocarea se comite înainte de eroarea către apelant.

Butonul „Verifică accesul” face numai `query OrdelyConnectionCheck { shop { id myshopifyDomain } }`; „Reînnoiește accesul” forțează refresh și aceeași verificare. Query validat prin instrumentul Shopify Admin GraphQL. Scopes sunt goale; comenzile/catalogul, aprobările PCD și implementarea CommerceConnector aparțin modulului 08. Registry nu pretinde că operațiile comerciale sunt deja disponibile.

## Webhookuri și operare

`POST /webhooks/shopify` verifică HMAC pe body brut înainte de parsare. Shop ID din payload trebuie să corespundă instalării, nu doar headerul de domeniu. Limita body este 32 KiB pentru aceste evenimente lifecycle. O cheie unică shop/delivery previne dublurile; același delivery cu body/topic diferit este conflict.

Inboxul lifecycle este separat de inboxul business din 05: payload criptat și revocare scurtă în aceeași tranzacție, înainte de ACK. Nu există apel extern sau prelucrare de comenzi în webhook. La timeout/deadlock se întoarce eroare și Shopify poate retrimite. Lock wait este limitat la 3 secunde. `app/uninstalled` revocă conexiunea curentă; un `X-Shopify-Triggered-At` anterior legării este ignorat, iar timestamp lipsă/invalid/viitor rămâne `needs_review`. Verificarea accesului detectează și pierderea autorizării când webhookul a lipsit. Păstrarea revocării funcționează inclusiv pentru tenants dezactivați.

`customers/data_request`, `customers/redact` și `shop/redact` sunt recepționate durabil, criptat, cu stare **needs_review**. Recepția nu înseamnă că solicitarea a fost executată. Panoul owner/admin afișează numărul; API-ul listează numai metadate, fără payload. Automatizarea îndeplinirii, retenția și procedura de ștergere/export sunt restante pentru pregătirea de lansare din 21 și trebuie stabilite înainte de orice import de date personale în 08/pilot. Nu publica aplicația în App Store cu această restanță.

Payloadurile webhook, tokenurile, codurile și secretele nu intră în audit. Serverul PHP de dezvoltare filtrează logurile HTTP, care altfel ar include ID token în query. Nu activa logarea request headers/body sau query strings pe serverul de producție. Reverse proxy de producție trebuie configurat explicit; în dezvoltare este acceptat numai proto de la loopback, prin launcher.

## Surse

[App structure și convenții CLI](https://shopify.dev/docs/apps/build/cli-for-apps/app-structure), [configurare aplicație](https://shopify.dev/docs/apps/build/cli-for-apps/app-configuration), [ID tokens](https://shopify.dev/docs/apps/build/authentication-authorization/id-tokens), [token exchange și refresh](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens), [verificarea webhookurilor](https://shopify.dev/docs/apps/build/webhooks/verify-deliveries).
