# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: [plan.md](plan.md).

**Modulele 01–07 sunt DONE. Niciun modul activ. Următorul: 08 — PLANNED, neînceput.**

## Shopify conectat

Aplicația **Ordely** este reinstalată și conectată exclusiv pe **[Ordely Shop (dev)](https://admin.shopify.com/store/ordely-shop/apps/ordely)**. Utilizatorul a finalizat personal Install după explicarea accesului standard la datele proprietarului. Preview-ul CLI HTTPS a fost refăcut după reinstalare.

Noua conexiune este activă, cu tokenuri criptate și refresh real verificat (versiune 2). Vechea conexiune rămâne revocată (versiune 3), iar webhookul real `app/uninstalled` este `processed`. App Bridge afișează „Magazin conectat la Ordely”. ANATOMIK live nu a fost folosit.

Modulul 07 include autorizare owner/admin Ordely + ID token Shopify, cod temporar, token exchange/refresh cu expirare, criptare, inbox lifecycle, deduplicare, revocare, protecție pentru evenimente vechi și UI minimal. Detalii: [fișa 07](docs/modules/07-shopify.md), [raport](docs/testing/07-shopify.md), [operare Shopify](docs/shopify.md).

## Verificări și publicare

170 teste locale PASS: 188 lint, PHPStan 8, 97 unit/651 assertions, 73 integration/367 assertions. Codul complet `348c578` este publicat și [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36410819306). La închiderea 07 nu s-a modificat codul; s-au verificat reinstalarea, noul token exchange, refresh-ul și stările conexiunilor, apoi documentația.

Scopes sunt goale, API `2026-07`. Importul comenzilor/catalogului și aprobările PCD urmează în 08. Cererile privacy au recepție durabilă `needs_review`; procedura de îndeplinire și retenție trebuie stabilită înainte de import de date personale/pilot. Nu este o lansare în producție/App Store.

## Mediu local și reluare

PHP 8.4.24 în `var/tools/php-8.4.24`, Composer 2.10.3, MySQL 8.4.11 pe 33060, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. Migrații 001–005 aplicate în app/test; keyring existent în `var/keys/keyring.json`, CA bundle local cu verificare TLS activă. `.env`, cheile, contul sintetic și tokenurile sunt ignorate de Git. XAMPP nemodificat.

MySQL și `shopify app dev --store ordely-shop.myshopify.com` rămân pornite. Panou local: `http://127.0.0.1:8080/`, cont sintetic în `var/dev-account.json`. Aplicația embedded și panoul local sunt lăsate deschise. Tunelul HTTPS depinde de procesele acestui PC și se schimbă la restart; pentru reluare urmează docs/shopify.md. Păstrează keyring-ul dacă păstrezi/restaurezi DB.

Remote `https://github.com/razvanstav/ordely.git`, branch `codex/modul-01-arhitectura`. Citește AGENTS, planul, deciziile și fișa 08 înainte de următoarea implementare. 08 nu a fost pornit automat.
