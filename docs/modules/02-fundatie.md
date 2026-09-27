# Modul 02 — Fundație tehnică

Actualizat: 2026-09-27. Stare: **IN_PROGRESS**. Dependență: modulul 01 DONE.

## Obiectiv și limite

Un checkout curat poate instala aceleași dependențe, porni PHP/MySQL și rula aceleași verificări. Componentele sunt infrastructură; nu implementăm Identity, Orders, Returns, providers sau schema lor.

Direcție tehnică: PHP 8.4, MySQL 8.4, Composer cu lockfile, Symfony 7.4 HttpFoundation/Routing/Dotenv, PHPUnit 12.5 și PHPStan 2. Runtime Docker Compose portabil; alternativ, PHP și MySQL native pe Windows.

## Pași și criterii de acceptare

- [ ] Composer manifest, lockfile și autoload PSR-4 reproducibile.
- [ ] Configurare exclusiv prin environment/fișier local ignorat, cu validare și fără secrete publicate.
- [ ] HTTP `/health` pentru proces și `/ready` pentru DB; indisponibilitatea DB produce 503 fără detalii sensibile.
- [ ] MySQL 8.4 verificat real: charset, UTC, prepared statements și rollback InnoDB.
- [ ] Lint, analiză statică, unit și integration tests trecute.
- [ ] Docker Compose și imagine PHP construite și verificate într-un mediu cu Docker.
- [ ] Clonă curată instalată din lockfile, fără fișiere locale ascunse necesare.
- [ ] GitHub Actions verde pe Linux cu MySQL și verificări de portabilitate pe Windows.
- [ ] Setup documentat, plan/status/jurnal actualizate și push verificat.

Mașina CI independentă validează reproducibilitatea pe un al doilea sistem. Nu pretindem acces sau test efectuat pe celălalt PC personal al utilizatorului; acesta va folosi aceleași instrucțiuni de clonare/setup.

## Verificări și predare

Rezultatele se înregistrează în `docs/testing/02-foundation.md`. Următorul modul, Identity & Tenancy, nu se începe în această sesiune.
