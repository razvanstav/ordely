# Verificare modul 03

2026-09-27, Windows, PHP 8.4.24, MySQL 8.4.11 nativ, baza separată `ordely_test`.

| Verificare executată | Rezultat |
| --- | --- |
| `php var/tools/composer.phar check` | PASS: validate, lint 32 fișiere, PHPStan level 8, 18 unit / 30 assertions, 17 integration / 90 assertions |
| `php vendor/bin/phpunit --filter IdentityHttpTest` | PASS: 27 assertions, server PHP separat, HTTP și DB reale |
| `php bin/migrate.php --test`, `php bin/migrate.php` | PASS: schema aplicată separat în test și dezvoltare |
| `node --check resources/app.js`, `git diff --check` | PASS |
| CI Windows / Linux Compose | În curs după push; nu este încă rezultat PASS |

Scenarii: două merchants cu magazine denumite identic, ID străin la repository/GET/PATCH, header/body tenant falsificat, FK grant cross-tenant respins, roluri fără scriere, grant retras, context owner vechi după demotion, cookie HttpOnly/Secure/SameSite, CSRF/origin/MIME, login generic/rate limit, switch autorizat și rotație sesiune, logout/expiry/dezactivări, provisionare atomică, migrare repetată/checksum modificat/eșec parțial.

Fluxul HTTP real execută login, creare magazin, listare, logout, refuzul sesiunii revocate și refuzul `.env`. Verifică absența parolei și a cookie-ului din logul serverului. Testul descoperise suprascrierea environment-ului în serverul PHP fără `variables_order=E`; bootstrap-ul importă explicit variabilele aplicației din OS înainte de Dotenv. Retestarea trece. Au existat și erori de analiză statică privind tipuri nullable, corectate fără suppressions.

Limite: interacțiunea manuală în browser și proba pe PC-ul personal secundar nu sunt efectuate. Nu există provider real, email de resetare sau onboarding public în acest modul.
