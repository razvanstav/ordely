# Starea curentă — Ordely

Actualizat: 2026-09-27.

## Punct de reluare

- **Ultimul modul finalizat:** [02 — Fundație tehnică](docs/modules/02-fundatie.md), `DONE`.
- Modulul 01 este DONE: cererea „CONTINUA” a fost interpretată și comunicată ca acord pentru următorul modul.
- Disponibil: PHP/MySQL, Composer lockfile, configurare, HTTP health/readiness, PHPUnit/PHPStan, Compose și workflow CI.
- **Modul activ:** [03 — Identity & Tenancy](docs/modules/03-identity-tenancy.md), REVIEW. Implementat și testat local; urmează commit/push și verificarea CI.
- Utilizatorul a autorizat continuarea secvențială până la 06 inclusiv în această sesiune. Modulele 04–21 sunt PLANNED; 07+ nu intră în lotul autorizat.

## Sincronizare între PC-uri

- Repository publicat pe branch-ul `codex/modul-01-arhitectura`, cu upstream `origin/codex/modul-01-arhitectura`.
- Remote `origin`: `https://github.com/razvanstav/ordely.git`.
- Primul commit al arhitecturii: `3a08834`. Push reușit; hash-ul local și cel returnat de remote au fost identice.
- Identitate locală configurată din contul GitHub autentificat: `razvanstav`, cu adresa GitHub noreply. Nu a fost modificată configurația globală Git.
- Utilizatorul a cerut explicit push după amânarea inițială; blocajul anterior este rezolvat.
- Pe alt PC: clonează repository-ul, deschide branch-ul de mai sus și citește acest fișier. Vezi [workflow-ul](docs/workflow.md).
- Fundația modulului 02 este publicată: cod final `fb25227`, [CI Windows și Linux finalizat cu succes](https://github.com/razvanstav/ordely/actions/runs/36343965209). [Setup](docs/setup.md) descrie instalarea pe alt PC; clona curată și instalările CI independente au trecut.

## Verificări și mediu

Raport curent: [modul 03](docs/testing/03-identity.md). Rapoartele 01/02 sunt istorice.

- PASS local 03: validate, 32 PHP lint, PHPStan level 8, 18 unit/30 assertions, 17 integration/90 assertions, inclusiv server HTTP separat. CI 03 urmează.
- PHP 8.4.24 în PATH, Composer în `var/tools/composer.phar`, MySQL 8.4.11 portabil în `var/tools`, date în `var/mysql`, `.env` ignorat. XAMPP/MariaDB a rămas neatins.
- Docker Compose a trecut build/start/test/HTTP în CI Linux. Windows CI a trecut instalarea MySQL nativ, toate testele și restartul. Proba pe al doilea PC personal nu este efectuată.
- MySQL Ordely rulează local pentru lotul 03–06; serverul HTTP temporar al testelor este oprit automat. Schema 001 este aplicată în dezvoltare/test. Comenzile de pornire sunt în setup.
- Execuția shell izolată a eșuat cu `apply deny-read ACLs`; citirile necesare au reușit prin execuția aprobată în afara sandbox-ului. Aceasta este o limitare a mediului de lucru, nu un defect al aplicației.

## Text pentru reluarea într-o conversație nouă

> Citește AGENTS.md, STATUS.md, plan.md și fișa modulului activ. Finalizează 03 cu teste și push, apoi 04, 05 și 06, câte unul. Oprește înainte de 07.
