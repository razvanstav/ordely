# Verificări — Modul 02

2026-09-27. Windows local, PHP 8.4.24, MySQL 8.4.11, Composer 2.10.3. Fără tabele de business sau conexiuni la furnizori comerciali.

## Rezultate locale

| Verificare executată | Rezultat |
| --- | --- |
| Installer Composer, SHA384 comparat cu sursa oficială | PASS |
| MySQL oficial, Authenticode Oracle | PASS |
| `php var/tools/composer.phar validate --strict` | PASS |
| `php var/tools/composer.phar lint` | PASS — 12 fișiere |
| `php var/tools/composer.phar analyse` | PASS — PHPStan level 8 |
| `php var/tools/composer.phar test` | PASS — 14 teste, 23 aserțiuni |
| `php var/tools/composer.phar test:integration` | PASS — 3 teste, 9 aserțiuni, MySQL real |
| `php var/tools/composer.phar check` | PASS — întreaga suită |
| `php var/tools/composer.phar db:check` | PASS — MySQL 8.4.11 |
| `php var/tools/composer.phar check-platform-reqs` | PASS |
| `php var/tools/composer.phar audit` | PASS — fără vulnerabilități raportate la rulare |
| `php bin/http-smoke.php` | PASS — 6 verificări HTTP reale |
| `./scripts/windows-mysql.ps1 -Action Start` repetat | PASS — instanța existentă, fără reset |
| Stop/Start helper + comparare `@@server_uuid` | PASS — oprire normală și aceeași instanță după restart |
| HTTP cu MySQL oprit, apoi repornit | PASS — health 200, ready 503 fără detalii; 6 checks PASS după restart |
| Clonă nouă din GitHub, `composer install` și `composer check` | PASS — 31 pachete din lockfile, fără modificări tracked |

Unit: health fără DB, readiness 503 fără scurgeri, metode HTTP, 404, HEAD, port/DSN/credentials și protecția bazei de test. Integration: versiune, UTC, utf8mb4, SQL strict, prepared statements native, Unicode, rollback InnoDB și readiness reală. HTTP: health/ready, HEAD, POST respins, `.env` și traversare către composer.json inaccesibile.

## Probleme remediate

XAMPP/MariaDB 10.4.32 nu a fost substituit pentru MySQL și a rămas nemodificat. Prima analiză PHPStan a depășit 128 MB; scriptul fixează 512 MB. Au fost corectate 10 probleme de tipuri PDOStatement/false, header HTTP și comparație redundantă; fără ignore/baseline.

Proba de shutdown a identificat lipsa contului root pentru TCP loopback cu skip-name-resolve. `mysqladmin ping` putea raporta procesul viu chiar fără autentificare. Helper-ul folosește acum `status` autenticat și configurează contul numai pentru 127.0.0.1. Recuperarea inițială a repornit strict perechea monitor/server din var/tools, identificată prin cale și config; XAMPP nu a fost oprit. După corecție, Stop/Start normal și păstrarea UUID-ului au trecut. CI Windows acoperă acum și acest helper.

## CI și reproductibilitate

- PASS la commit `96b33f6`: checkout curat local, CI Windows PHP și CI Linux Compose/MySQL/HTTP. [Rulare inițială](https://github.com/razvanstav/ordely/actions/runs/36343582815).
- PASS la commit `fb25227431053b1081e7ef60a3427c3225f8716c`: [CI final](https://github.com/razvanstav/ordely/actions/runs/36343965209), status completed, conclusion success.
- Windows PHP + native MySQL: instalare din checkout curat, toate cele 17 teste, platform requirements, oprire/repornire și shutdown final PASS.
- Linux PHP + MySQL + HTTP: build Compose, instalare lockfile, platform/audit, toate testele, HTTP și cleanup PASS.
- NOT_RUN: al doilea PC personal; CI independent a validat reproducibilitatea fără a pretinde acces la acel PC.

Verificarea de predare a validat 40 de linkuri locale și blocurile Markdown înainte de închiderea documentară; `.env`, var și vendor nu sunt tracked. Procesele locale create pentru teste au fost oprite normal, cu datele păstrate.

Modulul 02 este DONE. Limita rămasă este accesul la PC-ul personal secundar, nu un test eșuat: reproductibilitatea a fost demonstrată în clona locală curată și în două sisteme CI independente.
