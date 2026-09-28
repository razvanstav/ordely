# Modul 07 — Instalare și autentificare Shopify

2026-09-28. **DONE**. Continuarea autorizată prin „go”; exclusiv Ordely Shop (dev). ANATOMIK live este exclus.

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
- [x] Reinstalare finală reală efectuată de utilizator; noua conexiune activă și refresh-ul verificate, vechea conexiune păstrată revocată.
- [x] Publicare Git și CI Windows/Linux pentru implementarea completă, inclusiv corecția inventarului cheilor.

## Livrare

Migrația 005 păstrează ownership-ul shop/tenant/store, codurile de legare și inboxul lifecycle. Installations serializează exchange/refresh; callback-urile nu primesc adaptoare reutilizabile în afara verificării conexiunii. Perechea nouă de tokenuri se comite chiar dacă verificarea ulterioară are timeout. Uninstall funcționează și pentru merchant dezactivat. Generic bind/unbind nu poate muta instalările Shopify.

Inboxul lifecycle procesează revocarea scurtă în tranzacția de recepție. Cererile privacy se păstrează `needs_review`, fără a pretinde că sunt îndeplinite; implementarea exportului/ștergerii trebuie stabilită înainte de date personale în 08/pilot. Această limită este explicită în [operare](../shopify.md).

App ID public `62da15c72a85d17d1ee0cf8d7fa5058d`, Dev Dashboard app `428925190145`, API `2026-07`. `shopify.app.toml` are zero scopes și subscripții uninstall/compliance; CLI dev furnizează URL-ul HTTPS temporar. Nu s-a lansat aplicația în producție.

## Verificări

`php var/tools/composer.phar check`: PASS — 188 lint, PHPStan 8, 97 unit/651 assertions, 73 integration/367 assertions. GraphQL query-ul de verificare a magazinului și configurația CLI au fost validate. [Raport 07](../testing/07-shopify.md) separă probele reale de cele sintetice.

Pe dev store, refresh real a schimbat versiunea conexiunii 1→2; uninstall real a schimbat-o 2→3 și `revoked`. Payloadul este criptat, evenimentul `app/uninstalled` este `processed`. Shopify a raportat livrare în 280 ms, fără eșec.

## Predare

07 DONE. Utilizatorul a confirmat „gata, am dat install” și a finalizat personal reinstalarea după explicarea accesului standard la datele proprietarului. Restricția anterioară este rezolvată. Preview-ul CLI a fost repornit deoarece reinstalarea revenise la URL-ul inițial; apoi App Bridge a afișat „Magazin conectat la Ordely”.

Cod nou de legare consumat cu succes: conexiune nouă `active`, tokenuri criptate, scope gol. Refresh real după reinstalare PASS, versiune 1→2; conexiunea veche rămâne `revoked`, versiune 3, iar evenimentul uninstall rămâne `processed`. Captură locală în `var/ordely-shopify-reinstalled.png`, ignorată de Git.

Cod final `348c578` publicat, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36410819306). În această închidere s-au schimbat numai documentele și starea mediului de test; suita de cod nu a fost rerulată fără modificări. Browserul, PHP/tunelul CLI și MySQL rămân disponibile pe acest PC. 08 rămâne PLANNED și nu a fost pornit automat.

Reluare: citește AGENTS, STATUS, plan, decizii, docs/shopify.md și fișa 08; definește scopes/PCD și criteriile importului înainte de implementarea comenzilor/catalogului. Păstrează limitele privacy consemnate în operare și raport.