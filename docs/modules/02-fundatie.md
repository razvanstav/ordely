# Modul 02 — Fundație tehnică

Actualizat: 2026-09-27. Stare: **DONE**. Dependență: modulul 01 DONE.

## Obiectiv și limite

Un checkout curat poate instala aceleași dependențe, porni PHP/MySQL și rula aceleași verificări. Componentele sunt infrastructură; nu implementăm Identity, Orders, Returns, providers sau schema lor.

Direcție tehnică: PHP 8.4, MySQL 8.4, Composer cu lockfile, Symfony 7.4 HttpFoundation/Routing/Dotenv, PHPUnit 12.5 și PHPStan 2. Runtime Docker Compose portabil; alternativ, PHP și MySQL native pe Windows.

## Pași și criterii de acceptare

- [x] Composer manifest, lockfile și autoload PSR-4 reproducibile.
- [x] Configurare exclusiv prin environment/fișier local ignorat, cu validare și fără secrete publicate.
- [x] HTTP `/health` pentru proces și `/ready` pentru DB; indisponibilitatea DB produce 503 fără detalii sensibile.
- [x] MySQL 8.4 verificat real: charset, UTC, prepared statements și rollback InnoDB.
- [x] Lint, analiză statică, unit și integration tests trecute.
- [x] Docker Compose și imagine PHP construite și verificate în CI Linux.
- [x] Clonă curată instalată din lockfile, fără fișiere locale ascunse necesare.
- [x] GitHub Actions verde pe Linux/Compose și Windows/MySQL nativ, inclusiv restart.
- [x] Setup documentat, plan/status/jurnal actualizate; cod publicat și verificat pe GitHub.

Mașina CI independentă validează reproducibilitatea pe un al doilea sistem. Nu pretindem acces sau test efectuat pe celălalt PC personal al utilizatorului; acesta va folosi aceleași instrucțiuni de clonare/setup.

## Verificări și predare

Rezultate în [raport](../testing/02-foundation.md). Codul final al modulului este în commit `fb25227`; [CI complet verde](https://github.com/razvanstav/ordely/actions/runs/36343965209), ambele joburi finalizate cu succes. Închiderea documentară urmează în commit separat, fără schimbări de runtime.

Procesele locale pornite pentru teste au fost oprite normal; configurația și datele rămân în var/. Pornire conform [setup](../setup.md).

Următorul pas într-o conversație separată: modulul 03 — Identity & Tenancy. Se creează fișa modulului și criteriile pentru merchants, stores, memberships/roluri și izolarea tenant înainte de implementare. Modulul 03 nu a fost început.
