# Identitate și magazine — operare în dezvoltare

Rulează mai întâi `php bin/migrate.php`. Migrațiile sunt versionate în `database/migrations`; nu modifica fișiere aplicate. Runner-ul serializează execuțiile prin MySQL advisory lock și verifică checksum. DDL MySQL face commit implicit: o eroare lasă marcajul `applying`, iar reluarea se oprește. Inspectează schema și repară explicit înainte de schimbarea marcajului; nu șterge automat tabele sau date.

## Primul cont

În terminalul local setează temporar `ORDELY_MERCHANT`, `ORDELY_EMAIL`, `ORDELY_PASSWORD`, apoi execută `php bin/provision.php`. Folosește o parolă unică de 12–72 bytes, fără NUL. Comanda afișează ID-urile merchant/user/membership, niciodată parola. Nu pune parola în argumentele comenzii, Git, jurnal sau conversație. În PowerShell o poți introduce fără afișare prin `Read-Host -AsSecureString`, converti numai în proces și elimina variabila după comandă. Conturile existente cu același email sunt respinse atomic.

Pornește aplicația conform [setup](setup.md), deschide `http://127.0.0.1:8080/` și autentifică-te. Poți lista și crea magazine; endpoint-ul PATCH permite redenumirea. Magazinul este o identitate locală; conexiunea externă apare în 06/07.

## Acces prin CLI administrativ

`php bin/access.php COMANDĂ` este disponibil numai operatorului cu acces la DB/environment. Nu este endpoint public și nu implementează self-service. Administrarea utilizatorilor în dashboard și onboarding-ul complet apar în 20.

| Comandă | Variabile necesare |
| --- | --- |
| `create-user` | `ORDELY_EMAIL`, `ORDELY_PASSWORD`; afișează user ID |
| `add-member` | `ORDELY_MERCHANT_ID`, `ORDELY_USER_ID`, `ORDELY_ROLE`, `ORDELY_ALL_STORES` (`1` sau implicit `0`); afișează membership ID |
| `grant-store` / `revoke-store` | `ORDELY_MERCHANT_ID`, `ORDELY_MEMBERSHIP_ID`, `ORDELY_STORE_ID` |
| `disable-member` | `ORDELY_MERCHANT_ID`, `ORDELY_MEMBERSHIP_ID` |

Owner/admin pot gestiona magazine. Operator/finance/viewer citesc magazinele permise. Orice permisiune necunoscută este refuzată. Rolurile pentru comenzile comerciale se adaugă odată cu modulele lor. Un admin cu acces restrâns poate crea un magazin nou și primește grant explicit pentru acesta.

## HTTP și securitate

| Metodă | Rută | Comportament |
| --- | --- | --- |
| POST | `/api/auth/login` | `{email,password}`; cookie de sesiune și token CSRF |
| GET | `/api/me` | Merchant activ, rol, memberships active și CSRF |
| POST | `/api/auth/merchant` | `{merchantId}` autorizat; rotește sesiunea |
| POST | `/api/auth/logout` | Revocă sesiunea și șterge cookie |
| GET / POST | `/api/stores` | Listare / creare `{name,platform}` |
| GET / PATCH | `/api/stores/{id}` | Citire / redenumire `{name}` |

Scrierile cer JSON; toate în afară de login cer `X-CSRF-Token`. Origin, când este trimis, trebuie să coincidă cu origin-ul aplicației. Cookie host-only, HttpOnly, SameSite=Lax, TTL 8 ore, Secure pe HTTPS. `APP_ENV=prod` respinge HTTP. Proxy-urile nu sunt trusted implicit: terminarea TLS și configurarea unui proxy de încredere trebuie validate înainte de deployment; nu accepta automat X-Forwarded-Proto de la client.

DB păstrează numai hash SHA-256 al tokenului de sesiune aleator de 256 biți. Contextul este reconstruit din user, merchant și membership active la fiecare cerere. Tenant-ul trimis în body/header nu acordă acces. Schimbarea rolului, revocarea grantului sau dezactivarea membership-ului se aplică la următoarea cerere. FK-urile compuse resping relații între tenants; MySQL nu oferă automat row-level authorization.

Login are limită de 20 încercări/cont și 100/IP în 15 minute, cu contoare atomice DB și răspuns generic. Limitele sunt valori inițiale de dezvoltare, de evaluat la pilot. Nu există recuperare parolă prin email, MFA, SSO sau self-signup în acest modul. Retenția pentru sesiuni expirate și bucket-uri vechi se va lega de worker-ul de mentenanță; SQL administrativ poate elimina numai rânduri expirate.

Testele folosesc doar conturi sintetice `example.test`, MySQL real și server PHP separat. `composer check` pornește/oprește singur serverul HTTP temporar. Nu rulează teste contra unui provider extern.

Din modulul 05, POST /api/stores cere și Idempotency-Key. Replay returnează același ID, iar payload schimbat produce 409. [Operațiuni și worker](operations.md).
