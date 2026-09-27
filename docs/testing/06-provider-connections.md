# Verificare modul 06

2026-09-28. Windows, PHP 8.4.24, MySQL 8.4.11, teste în `ordely_test`.

| Verificare executată | Rezultat |
| --- | --- |
| `php var/tools/composer.phar check` | PASS: validate, 166 lint, PHPStan level 8, 73 unit/586 assertions, 56 integration/306 assertions |
| `php bin/migrate.php --test`, `php bin/migrate.php` | PASS: migrarea 004 aplicată; 001–003 neschimbate |
| `node --check resources/app.js`, `git diff --check` | PASS |
| `php bin/keyring.php` | PASS: fișier local ignorat generat, fără afișarea cheii |
| `php bin/key-status.php` | PASS: aplicația nu are încă provider connections |
| Generare/rotație CLI în suita unit | PASS: păstrează cheia veche, schimbă cheia activă, refuză suprascrierea și path traversal; permisiuni Unix verificate numai pe Linux CI |
| CI Windows/Linux | PASS: [run 36352683313](https://github.com/razvanstav/ordely/actions/runs/36352683313), cod 2358a26 |

Probe: nonce diferit la criptări identice; round trip; modificare ciphertext/nonce/tag, tag trunchiat, schimbare tenant/conexiune/provider/format/key ID, cheie lipsă/greșită/lungime invalidă, migrare la o cheie nouă și retragerea celei vechi din ring-ul de test. Serializarea/debug nu returnează secretele.

MySQL: credențiale criptate, creare repetată fără dubluri/audit duplicat, rotație și înlocuire token cu versiune, rollback la decriptare eșuată, FK între tenants și tipuri, default unic, dezasociere, rol/grant revocat. Două procese PHP pornesc simultan rotația aceleiași versiuni: exact unul reușește; celălalt primește conflict, fără suprascriere sau audit dublu.

Workerul refuză o conexiune revocată, închide jobul cu `scope_inactive` și nu apelează providerul. O a doua probă revocă după rezervarea operației, înainte de apel: verificarea repetată previne trimiterea. Recriptarea conexiunilor revocate nu le reactivează. Conexiunile fake existente nu sunt utilizabile în prod.

HTTP: creare/replay, asociere, capabilities, rotație/înlocuire, revocare, CSRF, roluri/all_stores, tenant străin și conflict de versiune. Server PHP separat cu keyring temporar aleator: flux real HTTP/MySQL, decriptare falsificată → 500 generic; tokenul și cheia lipsesc din răspunsuri și logul serverului. Fișierele și fixture-urile temporare sunt curățate.

NOT_RUN: interacțiune manuală vizuală în browser (driver indisponibil din cauza ACL-urilor sandbox) și PC-ul personal secundar. Testarea automată HTTP și sintaxa JS sunt PASS. Fără conturi/furnizori reali, fără audit criptografic extern ori certificare de producție; acestea nu sunt prezentate ca validate prin simulatoare.

Predare: verificarea linkurilor Markdown locale PASS; push confirmat prin compararea HEAD cu remote. Preview PHP8080 și MySQL33060 oprite după verificări; datele și cheia locală păstrate.
