# Verificare modul 05

2026-09-28. PHP 8.4.24, Windows, MySQL 8.4.11; fixtures numai în `ordely_test`.

| Verificare | Rezultat |
| --- | --- |
| `php var/tools/composer.phar check` | PASS: validate, 147 lint, PHPStan level 8, 65 unit/505 assertions, 41 integration/213 assertions |
| `php vendor/bin/phpunit --filter OperationsConcurrencyTest`, 5 rulări consecutive după corecție | PASS: două procese PHP reale pe rulare |
| `php bin/migrate.php --test`, `php bin/migrate.php` | PASS: 002 și 003, fără rescrierea migrațiilor aplicate |
| `php bin/worker.php 10` | PASS: pornire/oprire normală fără jobs eligibile |
| `node --check resources/app.js`, `git diff --check` | PASS |
| CI Windows/Linux | Urmează după push, încă nu este PASS |

Acoperire: atomicitate store/audit/outbox și rollback, inbox duplicate/mismatch, outbox replay, consumer commit înainte de ACK, doi workers, doi submitteri cu aceeași cheie HTTP și aceeași intenție externă, lease/heartbeat/fencing, scope inactiv/falsificat, retry-after/budget/dead/requeue, rol/grant revocate, payload allowlist și API fără lease tokens.

Crash-ul este real la nivel de proces PHP (`exit(24)` înainte, `exit(23)` după efect). Efectul extern este simulat printr-o scriere durabilă separată în DB de test. La reluare, UNKNOWN blochează callback-ul; confirmarea cu dovadă permite replay-ul rezultatului existent. Răspunsul întârziat nu suprascrie fencing version nouă. Retry cert temporar păstrează cheia și se oprește la 5 apeluri.

Defect găsit concurent: query-ul inițial putea examina/bloca mai multe jobs înainte de LIMIT din cauza indexului/sortării. Corectat prin migrarea 003, indexul potrivit, STRAIGHT_JOIN și FOR UPDATE OF j; cinci retestări și suita completă trecute. Fără suppressions PHPStan.

Limite: simulatorul nu confirmă un provider real. UI verificat prin HTTP și sintaxă JS, fără driver vizual disponibil. Cron/supervisor, quotas/performance de producție și politici fiscale sunt în modulele ulterioare/pilot.
