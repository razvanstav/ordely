# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: [plan.md](plan.md).

**Modulele 01–06 sunt DONE. Unicul modul activ: [07 — Shopify](docs/modules/07-shopify.md), REVIEW.** 08 nu a început.

## Implementare și verificări

Login Shopify CLI finalizat. Aplicația **Ordely** a fost instalată și conectată real exclusiv pe **[Ordely Shop (dev)](https://admin.shopify.com/store/ordely-shop)**. Token exchange offline cu expirare, refresh real, criptare și webhook real de dezinstalare sunt PASS. La dezinstalare, webhookul a revocat automat conexiunea; Dev Dashboard a raportat livrare fără eroare, 280 ms. ANATOMIK live nu a fost folosit.

Implementate: asociere autorizată owner/admin Ordely + ID token Shopify, cod temporar, tokenuri criptate și refresh serializat, inbox lifecycle, deduplicare, protecție pentru evenimente vechi, UI embedded și panou local. Detalii: [operare Shopify](docs/shopify.md), [raport 07](docs/testing/07-shopify.md).

Verificare locală completă PASS: 188 lint, PHPStan 8, 97 unit/651 assertions, 72 integration/364 assertions — 169 teste. Configurația Shopify și query-ul Admin GraphQL validate. CI pentru noul cod urmează după push.

## Pas rămas

Aplicația este momentan dezinstalată din Ordely Shop, după testul reușit. Reinstalarea este pregătită în browser. Shopify cere acces standard la datele proprietarului (nume, email, telefon, adresă), chiar cu scopes goale. Aprobarea automată a respins click-ul Install deoarece acest acces nu fusese explicit autorizat. Întrebarea a fost transmisă utilizatorului; nu relua instalarea prin CLI sau altă cale fără răspuns. Codul curent citește numai ID-ul și domeniul magazinului.

După aprobare: apasă Install pe Ordely Shop, deschide aplicația în preview-ul CLI, generează un cod Ordely nou, conectează și verifică păstrarea conexiunii vechi ca revocată. Apoi consemnează CI și închide 07. Nu începe 08 automat.

## Mediu local

PHP 8.4.24 în `var/tools/php-8.4.24`, Composer 2.10.3, MySQL 8.4.11 pe 33060, Node 24.19.0 din runtime Codex și Shopify CLI 4.8.2. Migrațiile 001–005 sunt aplicate în app/test; keyring în `var/keys/keyring.json`. PHP are CA bundle local, verificarea TLS activă. `.env`, cheile, contul sintetic și tokenurile sunt ignorate de Git. XAMPP nemodificat.

MySQL și preview-ul `shopify app dev --store ordely-shop.myshopify.com` sunt pornite. Panou local: `http://127.0.0.1:8080/`. Contul sintetic de probă este salvat numai în `var/dev-account.json`. Tunelul HTTPS depinde de procesele acestui PC și se schimbă la restart; nu reprezintă deployment de producție.

Remote `https://github.com/razvanstav/ordely.git`, branch `codex/modul-01-arhitectura`. Secretele nu se transferă prin Git. Reluare: citește AGENTS, planul, fișa/raportul 07 și docs/shopify.md; verifică răspunsul utilizatorului înainte de reinstalare.
