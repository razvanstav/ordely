# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: [plan.md](plan.md).

**Modulele 01–06 sunt DONE. Unicul modul activ: [07 — Shopify](docs/modules/07-shopify.md), IN_PROGRESS.** Continuarea a fost autorizată prin „go”. 08 nu a început.

## Pasul curent

Create **[Ordely Shop](https://admin.shopify.com/store/ordely-shop)**, magazin Shopify marcat `dev`, și aplicația **[Ordely](https://dev.shopify.com/dashboard/204104058/apps/428925190145)**. Utilizatorul a exclus explicit ANATOMIK live din teste. Aplicația are configurație inițială embedded/zero scopes, URL temporar `https://localhost:8080/shopify`, fără instalare în vreun magazin. URL-ul nu este un endpoint HTTPS funcțional.

Shopify CLI cere passkey-ul utilizatorului în browser. Următorul pas: finalizarea login-ului și legarea configurației. Secretele aplicației nu au fost extrase/salvate. Configurația locală și tokenurile nu se publică în Git sau chat.

Implementați și testați validatorii interni de domeniu Shopify, ID token HS256 și HMAC webhook. Nu sunt încă legați la rutele HTTP. Token exchange/refresh, persistență criptată, asociere tenant/store, UI embedded, inbox și dezinstalare rămân de implementat. Probele reale sunt NOT_RUN; [raport 07](docs/testing/07-shopify.md).

## Mediu pe acest PC

PHP 8.4.24 în `var/tools/php-8.4.24`, Composer 2.10.3 în `var/tools/composer.phar`, MySQL 8.4.11 separat de XAMPP. Dependențe din lockfile. PATH temporar PowerShell:

```powershell
$env:PATH=(Join-Path (Get-Location) 'var/tools/php-8.4.24')+';'+$env:PATH
```

Shopify CLI 4.8.2 cere Node >=22.12; verificat cu Node 24.19.0 inclus în Codex, deoarece Node 21 global nu este suportat. XAMPP nemodificat. `.env`, DB, cheile și dependențele sunt locale, ignorate de Git. Migrațiile 001–004 sunt aplicate în DB de test; setup-ul bazei aplicației și keyring-ul urmează înainte de utilizarea UI.

MySQL a fost oprit după teste, cu date păstrate. Repornire: `./scripts/windows-mysql.ps1 -Action Start`. Nu există preview PHP pornit.

Verificare completă locală după primul pas 07: PASS — 174 lint, PHPStan 8, 93 unit/629 assertions, 56 integration/306 assertions. Include 20 teste noi/43 assertions pentru autentificare. CI-ul închis al modulului 06: [Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36352683313), cod `2358a26`.

## Reluare

Remote: `https://github.com/razvanstav/ordely.git`. Branch: `codex/modul-01-arhitectura`. Fetch/pull înainte de lucru, conform [workflow](docs/workflow.md).

> Citește AGENTS.md, STATUS.md, plan.md, docs/decisions.md, docs/integrations.md și fișa/raportul 07. Continuă exclusiv 07. Ordely Shop este dev store-ul autorizat; nu instala sau testa pe ANATOMIK live. Finalizează autentificarea CLI cu utilizatorul, configurează un URL HTTPS de dezvoltare, apoi implementează token exchange/refresh criptat, asociere tenant/store, webhook inbox și dezinstalare. Testează local și pe dev store; documentează și publică înainte de DONE. Nu începe 08.
