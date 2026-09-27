# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: plan.md.

- **Ultimul modul închis:** [04 — Core și contracte](docs/modules/04-core-contracts.md), DONE.
- Cod: `5e0b747`; [CI Windows și Linux/MySQL/HTTP PASS](https://github.com/razvanstav/ordely/actions/runs/36348452800).
- Disponibil: runtime reproductibil, migrații, merchants/users/memberships, roluri și granturi, sesiuni revocabile, CSRF/rate limit, API și UI magazine, provisionare CLI.
- **Activ:** [05 — operațiuni durabile](docs/modules/05-durable-operations.md), REVIEW. Local PASS: 147 lint, 65 unit/505 assertions, 41 integration/213 assertions, PHPStan, JS, worker CLI și cinci retestări concurente. Urmează push/CI, apoi 06. Oprește înainte de 07.

## Reluare pe alt PC

Remote: https://github.com/razvanstav/ordely.git. Branch: `codex/modul-01-arhitectura`, upstream cu același nume. Identitatea Git locală este configurată din contul GitHub autentificat; se configurează separat pe fiecare PC. Urmează [workflow](docs/workflow.md), [setup](docs/setup.md), [identitate](docs/identity.md). Citește documentele după fetch/pull; nu sincroniza `.env` sau DB prin Git.

## Verificări și mediu

[Raport 04](docs/testing/04-core-contracts.md): 108 lint, PHPStan level 8, 65 unit/505 assertions, 17 integration/90 assertions PASS. Două checkout-uri CI independente au trecut. Migrațiile 001–003 sunt aplicate local în `ordely` și `ordely_test`.

PHP 8.4.24, Composer în `var/tools/composer.phar`, MySQL 8.4.11 în var pe loopback33060. MySQL și preview PHP8080 sunt pornite pentru lotul 03–06. Datele și `.env` sunt ignorate; XAMPP nu este atins. Serverele temporare ale testelor se opresc automat.

Interacțiunea manuală în browser și PC-ul personal secundar nu sunt verificate; driverul UI a eșuat la pornire din cauza ACL-urilor sandbox. Shell-ul funcționează prin execuția aprobată în afara sandbox-ului. Nu sunt defecte runtime restante; providerii reali și deciziile de business din modulele ulterioare rămân în afara scopului curent.

## Prompt de reluare

> Citește AGENTS.md, STATUS.md, plan.md, fișa modulului și deciziile. Modulul 04 este DONE. Implementează 05, testează/documentează/push, apoi repetă pentru 06. Oprește înainte de 07.

Core-ul și cele trei contracte/fakes sunt documentate în [core-contracts](docs/core-contracts.md). Fake-urile sunt exclusiv în memorie și nu demonstrează integrarea reală.
