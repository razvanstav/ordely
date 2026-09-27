# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: plan.md.

Ultimul modul închis: **05 — Operațiuni durabile, DONE**, cod `f04e71e`, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36350786805). Activ: **06 — Conexiuni și chei, REVIEW**. Cod și teste locale complete; urmează push/CI și închidere. Oprire înainte de 07.

Disponibil: runtime PHP/MySQL, identitate/tenancy, contracte, coadă durabilă/inbox/outbox/audit, idempotency/reconciliere și conexiuni criptate cu registru/asocieri/rotație/revocare. [Raport 06](docs/testing/06-provider-connections.md), [operare chei](docs/integrations.md).

## Reluare pe alt PC

Remote: https://github.com/razvanstav/ordely.git. Branch: `codex/modul-01-arhitectura`. Urmează [workflow](docs/workflow.md), [setup](docs/setup.md), [identitate](docs/identity.md). Fetch/pull înainte de lucru. `.env`, DB și cheile nu se sincronizează prin Git. DB nouă: generează propriile chei; restaurarea aceleiași DB cere separat keyring-ul ei.

## Mediu și verificări

PHP 8.4.24, MySQL 8.4.11, Composer în `var/tools/composer.phar`. Migrațiile 001–004 aplicate în ordely/ordely_test. Local PASS: 166 lint, PHPStan 8, 73 unit/586 assertions, 56 integration/306 assertions, JS și CLI chei. Keyring local în var/keys/keyring.json, ignorat și neafișat; aplicația nu are conexiuni reale.

MySQL pe loopback33060 și preview PHP8080 sunt pornite pentru lot; se opresc la predarea finală. XAMPP neatins. Testele își închid procesele și curăță fixture-urile.

Browser manual și al doilea PC personal: NOT_RUN. Driverul UI nu pornește din cauza ACL-urilor sandbox; API testat prin server HTTP real. Simulatoarele nu validează furnizori reali. Modulele 07–21 și deciziile de business D01–D05 rămân pentru continuare.

## Reluare

Citește AGENTS.md, plan.md, fișa 06, raportul și deciziile. Verifică CI pentru codul 06, închide documentele și publică predarea; oprește înainte de 07.
