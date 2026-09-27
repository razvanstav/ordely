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

Unit: health fără DB, readiness 503 fără scurgeri, metode HTTP, 404, HEAD, port/DSN/credentials și protecția bazei de test. Integration: versiune, UTC, utf8mb4, SQL strict, prepared statements native, Unicode, rollback InnoDB și readiness reală. HTTP: health/ready, HEAD, POST respins, `.env` și traversare către composer.json inaccesibile.

## Probleme remediate

XAMPP/MariaDB 10.4.32 nu a fost substituit pentru MySQL și a rămas nemodificat. Prima analiză PHPStan a depășit 128 MB; scriptul fixează 512 MB. Au fost corectate 10 probleme de tipuri PDOStatement/false, header HTTP și comparație redundantă; fără ignore/baseline.

## Verificări în curs

- NOT_RUN: clonă curată.
- NOT_RUN: build/test Compose; Docker nu este instalat pe PC-ul curent, se verifică în CI Linux.
- NOT_RUN: pipeline GitHub Actions Windows/Linux.
- NOT_RUN: al doilea PC personal; CI independent va valida reproducibilitatea fără a pretinde acces la acel PC.
