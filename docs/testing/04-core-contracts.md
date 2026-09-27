# Verificare modul 04

2026-09-27, Windows, PHP 8.4.24, MySQL 8.4.11.

| Comandă | Rezultat |
| --- | --- |
| `php vendor/bin/phpunit --filter MoneyTest` | PASS: 15 teste, 235 assertions înaintea porturilor |
| `php var/tools/composer.phar check` | PASS: validate, 108 fișiere lint, PHPStan level 8, 65 unit/505 assertions, 17 integration/90 assertions |
| `git diff --check` | PASS |
| CI Windows/Linux | PASS, cod `5e0b747`, [run 36348452800](https://github.com/razvanstav/ordely/actions/runs/36348452800) |

Contract tests acoperă toate cele trei interfețe, paginare, date normalizate, aceeași cheie/replay, payload schimbat/conflict, merchant/store/connection diferit, funcții nepermise, referințe de linii invalide, credit peste total și timeout după efect. PDF/ZPL sunt marcate de test; trimiterea facturii este numai o înregistrare în memorie.

Teste monetare: precizii 0/2/3, input invalid, limită și overflow, monede/exponenți incompatibili, half-away-from-zero și alocarea exactă a 201 valori pozitive/negative. CoreValidation respinge DTO-uri inconsistente, metadata arbitrară și float în payload-ul canonic. ArchitectureTest verifică dependențele Core și confirmă că detectează importuri interzise introduse într-o probă negativă.

Erori intermediare remediate: adnotări PHPDoc multiple pe aceeași linie, verificarea contorului mutabil și tokenizarea contextuală pentru metoda `require`. Fără baseline sau ignorări PHPStan. Contract tests folosesc fakes; niciun provider extern nu a fost testat.
