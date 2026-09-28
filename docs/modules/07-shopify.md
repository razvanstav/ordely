# Modul 07 — Instalare și autentificare Shopify

2026-09-28. **IN_PROGRESS**. Modulul 06 este închis la `9982a70`. Continuarea modulului 07 a fost autorizată prin „go”.

## Obiectiv și limite

Instalare embedded cu App Bridge și token exchange în adaptorul PHP, asociere explicită cu merchant/store Ordely, tokenuri offline criptate cu expirare/refresh, webhook inbox și dezinstalare. Importul comenzilor/catalogului aparține modulului 08; nu implementăm aceste operații aici. Nu publicăm în App Store în acest modul.

## Criterii stabilite înainte de cod

- [x] Validator intern ID token HS256: semnătură, audiență, expirare, nbf, issuer/destinație Shopify; domenii strict validate. Legarea la rutele HTTP rămâne în pasul 2.
- [ ] Conectarea cere atât identitate Shopify validă, cât și autorizarea owner/admin Ordely pentru merchant/store; asocierea nu poate fi preluată de alt tenant.
- [ ] Token exchange cere tokenuri offline cu expirare; access/refresh token criptate, expiry din răspuns, refresh serializat și reautorizare la revocare.
- [ ] Secretele nu apar în răspunsuri, audit, loguri sau Git; nici tokenul App Bridge nu este salvat în clar.
- [ ] Webhook HMAC pe body brut, deduplicare persistentă, recepție durabilă înainte de ACK, izolare shop/connection și dezinstalare care blochează utilizarea conexiunii.
- [ ] Reinstalarea nu permite unui eveniment vechi să revoce conexiunea nouă; payload-urile sensibile rămân criptate.
- [ ] UI minimal pentru inițiere/conectare/stare; CSP separat pentru suprafața embedded, fără dependență de cookies third-party.
- [ ] Teste unitare, MySQL/HTTP, concurență și regresii; verificări locale și CI consemnate.
- [ ] Dev store real: instalare, token exchange/refresh, livrare webhook și dezinstalare verificate; scopes/PCD și configurația aplicației confirmate.

## Pași

1. Verificare documentație actuală, acces dev store și mediu local.
2. Autentificare/legare sigură, persistență criptată și refresh.
3. Webhook lifecycle, interfață minimală, teste și documentare.
4. Validare dev store, commit/push și CI; numai apoi DONE.

## Verificări și dependențe

Checkout curat și `git pull --ff-only`: PASS, deja actualizat. Pregătite pe acest PC: PHP 8.4.24 în `var/tools/php-8.4.24`, Composer 2.10.3, MySQL 8.4.11 și dependențele din lockfile. Regresiile inițiale PASS: 166 lint, PHPStan 8, 73 unit/586 assertions, 56 integration/306 assertions.

Utilizatorul a precizat că ANATOMIK este live și a autorizat un magazin separat. Dev Dashboard nu lista niciun dev store. Au fost create:

- **[Ordely Shop](https://admin.shopify.com/store/ordely-shop)**, badge `dev` verificat. Basic, date fictive solicitate, fără feature previews; inventarul datelor generate nu este încă verificat.
- **[Ordely](https://dev.shopify.com/dashboard/204104058/apps/428925190145)**, versiunea inițială `module-07-dev`, embedded, zero scopes, fără instalare în vreun magazin. URL temporar `https://localhost:8080/shopify`; nu este un endpoint HTTPS funcțional.

Shopify CLI 4.8.2 instalat, verificat cu Node 24.19.0 inclus în Codex; Node 21 global nu este suportat. `app config link` a ajuns la verificarea contului prin passkey, pe care utilizatorul trebuie să o finalizeze în browser. Nu au fost extrase secretele aplicației. Conectorul merchant existent nu a fost schimbat de la magazinul live.

Adăugate biblioteci interne: ShopDomain, AppConfig, IdTokenVerifier, ShopIdentity, InvalidIdToken și WebhookSignature, fără rute HTTP încă. În dev este obligatoriu un domeniu permis explicit; identitatea Shopify nu acordă automat drepturi Ordely. [Raportul 07](../testing/07-shopify.md) separă testele locale de cele reale restante.

Verificare completă după cod: `php var/tools/composer.phar check` PASS — 174 lint, PHPStan 8, 93 unit/629 assertions, 56 integration/306 assertions.

## Predare

Unicul modul activ: 07, IN_PROGRESS. Branch: `codex/modul-01-arhitectura`. Următorul pas: finalizarea login-ului CLI, reluarea `shopify app config link` dacă sesiunea a expirat, configurarea secretelor în `.env` ignorat și a unui URL HTTPS de dezvoltare. Apoi token exchange/refresh criptat, asociere tenant/store, endpoint-uri/UI, inbox și dezinstalare. Nu instala Ordely pe ANATOMIK. Testele pe dev store sunt obligatorii înainte de DONE; 08 nu începe.
