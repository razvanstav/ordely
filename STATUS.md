# Starea curentă — Ordely

Actualizat: 2026-09-27.

## Punct de reluare

- **Ultimul modul finalizat:** [02 — Fundație tehnică](docs/modules/02-fundatie.md), `DONE`.
- Modulul 01 este DONE: cererea „CONTINUA” a fost interpretată și comunicată ca acord pentru următorul modul.
- Disponibil: PHP/MySQL, Composer lockfile, configurare, HTTP health/readiness, PHPUnit/PHPStan, Compose și workflow CI.
- **Următorul pas:** modulul 03 — Identity & Tenancy, în conversație separată. Începe prin fișa și criteriile de acceptare; nu există implementare pentru el încă.
- Modulele comerciale 03–21 nu sunt începute. Deciziile de business rămân deschise până la modulele lor.

## Sincronizare între PC-uri

- Repository publicat pe branch-ul `codex/modul-01-arhitectura`, cu upstream `origin/codex/modul-01-arhitectura`.
- Remote `origin`: `https://github.com/razvanstav/ordely.git`.
- Primul commit al arhitecturii: `3a08834`. Push reușit; hash-ul local și cel returnat de remote au fost identice.
- Identitate locală configurată din contul GitHub autentificat: `razvanstav`, cu adresa GitHub noreply. Nu a fost modificată configurația globală Git.
- Utilizatorul a cerut explicit push după amânarea inițială; blocajul anterior este rezolvat.
- Pe alt PC: clonează repository-ul, deschide branch-ul de mai sus și citește acest fișier. Vezi [workflow-ul](docs/workflow.md).
- Fundația modulului 02 este publicată: cod final `fb25227`, [CI Windows și Linux finalizat cu succes](https://github.com/razvanstav/ordely/actions/runs/36343965209). [Setup](docs/setup.md) descrie instalarea pe alt PC; clona curată și instalările CI independente au trecut.

## Verificări și mediu

Raport curent: [modul 02](docs/testing/02-foundation.md). Raportul modulului 01 rămâne istoric.

- PASS: validate, 12 PHP lint, PHPStan level 8, 14 unit tests/23 assertions, 3 integration tests/9 assertions, 6 HTTP checks, platform requirements și audit.
- PHP 8.4.24 în PATH, Composer în `var/tools/composer.phar`, MySQL 8.4.11 portabil în `var/tools`, date în `var/mysql`, `.env` ignorat. XAMPP/MariaDB a rămas neatins.
- Docker Compose a trecut build/start/test/HTTP în CI Linux. Windows CI a trecut instalarea MySQL nativ, toate testele și restartul. Proba pe al doilea PC personal nu este efectuată.
- Procesele PHP/MySQL pornite local pentru teste au fost oprite; datele și configurația se păstrează. Comenzile de pornire sunt în setup.
- Execuția shell izolată a eșuat cu `apply deny-read ACLs`; citirile necesare au reușit prin execuția aprobată în afara sandbox-ului. Aceasta este o limitare a mediului de lucru, nu un defect al aplicației.

## Text pentru reluarea într-o conversație nouă

> Citește AGENTS.md, STATUS.md, plan.md, docs/setup.md și deciziile relevante. Modulul 02 este DONE. Începe numai modulul 03 — Identity & Tenancy: creează fișa și criteriile, apoi implementează și testează izolarea între tenants. Actualizează documentele și fă push după verificări.
