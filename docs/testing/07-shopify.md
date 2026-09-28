# Verificări 07 — Shopify

2026-09-28. Modul REVIEW. Windows, PHP 8.4.24, Composer 2.10.3, MySQL 8.4.11, Node 24.19.0, Shopify CLI 4.8.2.

| Comandă / scenariu | Rezultat | Dovadă / limitare |
| --- | --- | --- |
| `git pull --ff-only` | PASS | Branch existent, deja actualizat înainte de implementare |
| `php var/tools/composer.phar check` | PASS | 188 lint, PHPStan 8, 97 unit/651 assertions, 73 integration/367 assertions |
| Shopify unit/integration/HTTP | PASS | 41 teste, inclusiv validatori din primul pas; integrarea folosește MySQL și gateway sintetic |
| Două procese PHP concurente | PASS | Un singur refresh pentru două operații pe token expirat; versiunea crește o dată |
| `node --check resources/app.js` și `resources/shopify.js` | PASS | JavaScript nativ |
| `shopify app config link` | PASS | Login confirmat de utilizator; configurație descărcată |
| `shopify app env pull` | PASS | Secrete în .env ignorat; fără publicare |
| `shopify app config validate --json` | PASS | valid true, issues goale |
| Validator Shopify Admin GraphQL | PASS | `query OrdelyConnectionCheck { shop { id myshopifyDomain } }`, artifact ordely-shopify-connection-check revision 1 |
| Migrații app/test 001–005, keyring local | PASS | Aplicate; fixture cleanup actualizat |
| CLI dev HTTPS → Ordely Shop | PASS | Tunel Cloudflare, app embedded; problemele inițiale de URL și CA local corectate |
| Browser Ordely local | PASS | Login cont sintetic, magazin Shopify, generare cod, stare conexiune și buton refresh |
| App Bridge și token exchange reale | PASS | Magazinul afișează conectat; răspunsul API verifică ID/domeniu |
| Offline refresh real | PASS | Buton reînnoire, versiune 1→2; access TTL 3600 s, refresh TTL 7776000 s, scope gol |
| Criptare în DB reală | PASS | Decriptare validă; valorile tokenurilor absente din envelope în clar |
| Dezinstalare reală și webhook | PASS | 2026-09-28 10:24:11 UTC: app/uninstalled processed, conexiune revoked, versiune 3; Dashboard Shopify 280 ms, 0% eșec |
| Reinstalare finală reală | BLOCKED | Ecran Install cere datele proprietarului; auto-review a solicitat autorizare explicită. Întrebare trimisă utilizatorului |
| CI implementare și inventar chei, cod 348c578 | PASS | [Windows/Linux/MySQL/HTTP](https://github.com/razvanstav/ordely/actions/runs/36410819306) |
| CI primul pas, cod 8187f00 | PASS | [Windows/Linux](https://github.com/razvanstav/ordely/actions/runs/36397390326) |

Cazuri automate: JWT expirat/malformat/algoritm/audiență/issuer greșit; SSRF/domenii externe; raw HMAC; schimb tokenuri cu expiring=1; erori API sanitizate; cod expirat; roluri și membership revocat; izolare cross-store/tenant inclusiv după revocare; refuz bind generic; retry idempotent; refresh urmat de timeout; pierderea autorizării; duplicate și conflict webhook; payload shop ID greșit; uninstall după reinstalare cu timestamp vechi/lipsă; uninstall pentru merchant dezactivat; payload privacy criptat și pending; CSP/CSRF/bearer HTTP.

Limitări: importul de comenzi/catalog nu este implementat și nu a fost testat; PCD nu a fost cerut sau aprobat. Privacy este recepție durabilă cu procesare explicit restantă, nu conformitate App Store completă. Testele concurente folosesc API sintetic; refresh-ul API real este o probă separată. Reinstalarea cu eveniment vechi este acoperită automat, proba finală din browser așteaptă acordul utilizatorului.
