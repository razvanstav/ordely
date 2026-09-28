# Modul 07 — Instalare și autentificare Shopify

2026-09-28. **REVIEW**. Continuarea autorizată prin „go”; exclusiv Ordely Shop (dev). ANATOMIK live este exclus.

## Obiectiv și limite

Instalare embedded cu App Bridge și token exchange PHP, asociere explicită merchant/store Ordely, tokenuri offline criptate cu expirare/refresh, inbox lifecycle și dezinstalare. Importul comenzilor/catalogului aparține 08; publicarea App Store și procedurile complete privacy aparțin pregătirii de lansare.

## Criterii stabilite înainte de cod

- [x] ID token HS256 validat: semnătură, audiență, timp, issuer/destinație; domenii canonice și allowlist dev.
- [x] Identitate Shopify plus owner/admin Ordely autorizat; asociere unică și imposibil de preluat de alt tenant.
- [x] Offline expiring token exchange, criptare access/refresh/scopes/expiry, refresh serializat și revocare la pierderea autorizării.
- [x] Tokenurile nu sunt returnate în API/UI sau audit; codurile sunt stocate numai hash; logul serverului dev filtrează query strings.
- [x] HMAC raw body, inbox criptat/deduplicat și commit înainte de ACK; dezinstalare cu blocarea accesului.
- [x] Reinstalare simulată și evenimente vechi testate; payloadurile lifecycle rămân criptate.
- [x] UI pentru inițiere/conectare/stare/refresh, CSP embedded și bearer tokens fără cookies third-party.
- [x] Teste PHP/MySQL/HTTP, concurență în două procese și regresii locale.
- [x] Dev store real: instalare, App Bridge, token exchange, refresh, webhook și dezinstalare PASS; zero scopes, fără PCD solicitat.
- [ ] Reinstalare finală reală și predare cu conexiune activă: așteaptă autorizarea accesului standard la datele proprietarului.
- [ ] Publicare Git și CI pentru implementarea completă.

## Livrare

Migrația 005 păstrează ownership-ul shop/tenant/store, codurile de legare și inboxul lifecycle. Installations serializează exchange/refresh; callback-urile nu primesc adaptoare reutilizabile în afara verificării conexiunii. Perechea nouă de tokenuri se comite chiar dacă verificarea ulterioară are timeout. Uninstall funcționează și pentru merchant dezactivat. Generic bind/unbind nu poate muta instalările Shopify.

Inboxul lifecycle procesează revocarea scurtă în tranzacția de recepție. Cererile privacy se păstrează `needs_review`, fără a pretinde că sunt îndeplinite; implementarea exportului/ștergerii trebuie stabilită înainte de date personale în 08/pilot. Această limită este explicită în [operare](../shopify.md).

App ID public `62da15c72a85d17d1ee0cf8d7fa5058d`, Dev Dashboard app `428925190145`, API `2026-07`. `shopify.app.toml` are zero scopes și subscripții uninstall/compliance; CLI dev furnizează URL-ul HTTPS temporar. Nu s-a lansat aplicația în producție.

## Verificări

`php var/tools/composer.phar check`: PASS — 188 lint, PHPStan 8, 97 unit/651 assertions, 72 integration/364 assertions. GraphQL query-ul de verificare a magazinului și configurația CLI au fost validate. [Raport 07](../testing/07-shopify.md) separă probele reale de cele sintetice.

Pe dev store, refresh real a schimbat versiunea conexiunii 1→2; uninstall real a schimbat-o 2→3 și `revoked`. Payloadul este criptat, evenimentul `app/uninstalled` este `processed`. Shopify a raportat livrare în 280 ms, fără eșec.

## Predare

Testul de reinstalare a ajuns la Install pe Ordely Shop. Aprobarea automată a respins acțiunea deoarece Shopify cere acces standard la nume/email/telefon/adresa proprietarului, neautorizat explicit anterior. Confirmarea este cerută utilizatorului; nu ocoli blocarea prin CLI. După aprobare, finalizează reinstalarea și legarea cu un cod nou, verifică conexiunea veche revocată, apoi CI și închiderea 07. 08 rămâne PLANNED.
