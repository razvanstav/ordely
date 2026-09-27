# Starea curentă — Ordely

Actualizat: 2026-09-28. Registrul stărilor: plan.md.

**Modulele 01–06 sunt DONE. Niciun modul activ.** Ultimul: [06 — Conexiuni și chei](docs/modules/06-provider-connections.md). Cod `2358a26`, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36352683313). Lotul autorizat este închis. **07 — Shopify este PLANNED, neînceput.**

Disponibil: runtime PHP/MySQL, identitate/tenancy, contracte și simulatoare, coadă durabilă/inbox/outbox/audit, idempotency/reconciliere, conexiuni criptate cu registru/asocieri/rotație/revocare și interfață minimală. [Raport 06](docs/testing/06-provider-connections.md), [operare chei](docs/integrations.md).

## Reluare pe alt PC

Remote: https://github.com/razvanstav/ordely.git. Branch: `codex/modul-01-arhitectura`. Urmează [workflow](docs/workflow.md), [setup](docs/setup.md), [identitate](docs/identity.md). Fetch/pull înainte de lucru; documentele versionate sunt sursa progresului, fără dependență de conversația veche.

`.env`, DB și cheile nu se sincronizează prin Git. DB nouă: generează propriile chei; restaurarea aceleiași DB cere separat keyring-ul ei. Nu publica cheile în Git sau chat.

## Mediu și verificări

PHP 8.4.24, MySQL 8.4.11, Composer în `var/tools/composer.phar`. Migrațiile 001–004 aplicate în ordely/ordely_test. Local PASS: 166 lint, PHPStan 8, 73 unit/586 assertions, 56 integration/306 assertions, JS, CLI chei și linkuri Markdown. CI Windows și Linux verde pentru codul final.

**Serverele temporare sunt oprite**, cu datele păstrate. Pornire: `./scripts/windows-mysql.ps1 -Action Start`, apoi `php var/tools/composer.phar serve`. Keyring local în var/keys/keyring.json, ignorat și neafișat. Nu există conexiuni reale configurate. XAMPP nu a fost modificat de această lucrare.

Browser manual și al doilea PC personal: NOT_RUN. Driverul UI nu pornește din cauza ACL-urilor sandbox; API testat prin server HTTP real. Simulatoarele nu validează furnizori reali. Deciziile de business D01–D05 și accesul real D08 se rezolvă înainte de modulele dependente.

## Prompt pentru o conversație viitoare

> Citește AGENTS.md, STATUS.md, plan.md, docs/decisions.md, docs/integrations.md și predarea modulului 06. Modulele 01–06 sunt închise și publicate. Începe numai modulul 07 — Shopify, cu criterii înainte de cod și verificarea accesului la dev store. Testează, documentează și publică înainte de a propune următorul modul.
