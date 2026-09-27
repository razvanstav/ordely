# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: plan.md.

Ultimul modul închis: **05 — Operațiuni durabile, DONE**. Cod `f04e71e`, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36350786805). Urmează 06, ultimul din lotul autorizat; oprire înainte de 07.

Disponibil: runtime PHP/MySQL, identitate/tenancy, contracte și fakes, coadă durabilă, inbox/outbox/audit, idempotency și reconciliere UNKNOWN. Vezi [raport 05](docs/testing/05-durable-operations.md) și [operare](docs/operations.md).

## Reluare pe alt PC

Remote: https://github.com/razvanstav/ordely.git. Branch: `codex/modul-01-arhitectura`. Urmează [workflow](docs/workflow.md), [setup](docs/setup.md), [identitate](docs/identity.md). Fetch/pull înainte de lucru; `.env`, datele și cheile nu se sincronizează prin Git.

## Mediu și verificări

PHP 8.4.24, MySQL 8.4.11, Composer în `var/tools/composer.phar`. Migrațiile 001–003 aplicate în `ordely` și `ordely_test`. Local PASS: 147 lint, PHPStan 8, 65 unit/505 assertions, 41 integration/213 assertions, cinci retestări concurente, JS și worker CLI. MySQL pe loopback33060 și preview PHP8080 sunt pornite pentru lot; XAMPP neatins.

Browser manual și al doilea PC personal: NOT_RUN. Driverul UI nu pornește din cauza ACL-urilor sandbox; API-ul este testat prin HTTP real. Fakes și simulatorul de efecte externe nu demonstrează integrări reale.

## Reluare

Citește AGENTS.md, plan.md, fișa 05 și deciziile. Definește criteriile 06, implementează, testează, documentează și publică; oprește înainte de 07.
