# Pornirea Ordely

Acesta este un mediu de dezvoltare. Starea modulelor este în [plan](../plan.md). Identitatea, sesiunile și magazinele au ghid separat în [identity](identity.md).

## Versiuni fixate

PHP 8.4.24, MySQL 8.4.11, Composer 2.10.3. Symfony 7.4 (patch-urile în composer.lock), PHPUnit 12.5.36 și PHPStan 2.2.16. MariaDB/XAMPP nu înlocuiește MySQL în teste.

Instalările normale folosesc `composer install`, fără `update`. Schimbarea versiunilor este o modificare explicită, testată și comisă împreună cu lockfile-ul. PHPStan are limita de memorie fixată la 512 MB în scriptul Composer.

## Pe alt PC

```powershell
git clone --branch codex/modul-01-arhitectura https://github.com/razvanstav/ordely.git
cd ordely
```

Numele branch-ului se păstrează pentru continuitate; modulul curent este cel din `plan.md`. Dacă există deja o clonă, verifică modificările locale și urmează [workflow-ul](workflow.md).

## Docker Compose

Necesită Docker cu containere Linux și Compose v2. Pe Windows poate fi Docker Desktop cu WSL2; proiectul nu îl instalează automat.

Copiază `.env.example` în `.env` fără a suprascrie un fișier existent (`Copy-Item .env.example .env` în PowerShell sau `cp .env.example .env` pe Linux/macOS). Pentru date locale reale, înlocuiește parolele demonstrative înainte de prima inițializare. `.env` este ignorat de Git.

```text
docker compose build
docker compose up -d --wait db
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app composer check
docker compose run --rm app composer db:migrate
docker compose up -d --wait app
docker compose exec -T app php bin/http-smoke.php
```

Verificări: [health](http://127.0.0.1:8080/health) pentru procesul PHP și [ready](http://127.0.0.1:8080/ready) pentru MySQL. Singurul document root este `public/`.

MySQL ascultă pe PC la `127.0.0.1:33060`, iar în rețeaua containerelor la `db:3306`. Compose fixează host/port/numele bazelor în container; porturile expuse se pot schimba prin APP_PORT/DB_PORT. Cu APP_PORT diferit, adaptează URL-ul browserului; smoke-ul din container continuă pe portul intern 8080.

`docker compose down` oprește containerele și păstrează volumul MySQL. Schimbarea parolelor în `.env` după inițializare nu schimbă automat userii existenți; actualizează-i explicit. Nu șterge volumul ca soluție pentru un conflict de configurare dacă are date de păstrat.

## Windows nativ

Necesită PHP 8.4 în PATH, cu PDO MySQL, DOM, XML/XMLWriter, tokenizer, mbstring, OpenSSL și cURL. Pe PC-ul inițial PHP este disponibil. Helper-ele folosesc numai `var/`, fără servicii Windows sau modificarea XAMPP.

```powershell
./scripts/install-composer.ps1
./scripts/windows-mysql.ps1 -Action Start
php var/tools/composer.phar install --no-interaction --prefer-dist
php var/tools/composer.phar check
php bin/migrate.php
php var/tools/composer.phar serve
```

În alt terminal:

```powershell
php bin/http-smoke.php
php var/tools/composer.phar db:check
```

Helper-ul MySQL descarcă arhiva Oracle 8.4.11 (aproximativ 281 MB) dacă lipsește, apoi verifică semnătura executabilului. Creează `.env` cu parole aleatoare numai dacă lipsește; inițializează bazele `ordely` și `ordely_test` cu un user local. Acceptă deliberat profilul local simplu din `.env.example` și păstrează datele la repornire.

Serverul este legat numai de 127.0.0.1:33060; dacă portul este ocupat de alt proces, helper-ul se oprește fără a-l modifica. Log: `var/mysql/mysql-error.log`. `var/mysql` include credențiale locale de control și nu se publică sau copiază în documentele de predare. Nu folosi helper-ul pentru producție.

Oprește MySQL cu `./scripts/windows-mysql.ps1 -Action Stop`; datele rămân. Serverul PHP pornit în terminal se oprește cu Ctrl+C. Helper-ele rezolvă căile relativ la propriul fișier. Nu porni Compose și varianta nativă pe același port.

## MySQL deja instalat

Folosește MySQL 8.4, o bază de dezvoltare și una separată cu sufix `_test`. Creează un user cu drepturi numai pe aceste baze și configurează DB_HOST, DB_PORT, DB_NAME, DB_TEST_NAME, DB_USER, DB_PASSWORD în `.env` sau environment. Helper-ul Windows este opțional.

Testele de integrare verifică sufixul DB_TEST_NAME și aplică schema reală în baza de test. Fixture-urile se șterg prin rollback sau cleanup al ID-urilor proprii; testul HTTP pornește separat PHP și elimină numai contul său sintetic. Nu folosi o bază cu date reale ca DB_TEST_NAME. Lipsa conexiunii este eșec, fără skip sau SQLite.

## Verificări

| Comandă Composer | Scop |
| --- | --- |
| `validate --strict` | Manifest și lockfile |
| `lint` | Sintaxă PHP în bin/config/public/src/tests |
| `analyse` | PHPStan level 8 |
| `test` | Teste unitare, fără DB obligatorie |
| `test:integration` | MySQL real, migrații, izolare, sesiuni și flux HTTP prin server temporar |
| `db:migrate` | Aplică migrațiile bazei aplicației; testele migrează separat baza `_test` |
| `check` | Validate + lint + analyse + unit + integration |
| `check-platform-reqs` | PHP și extensiile instalate efectiv |
| `audit` | Advisories la momentul rulării |

Prefix local: `php var/tools/composer.phar`; dacă există în PATH, `composer`. `php bin/http-smoke.php` cere serverul deja pornit și acceptă URL alternativ ca prim argument.

CI verifică PHP și helper-ul MySQL nativ (inclusiv restart) pe Windows și construiește/rulează Compose cu MySQL/HTTP pe Linux, în checkout-uri independente. Rezultate: [raportul 02](testing/02-foundation.md). PC-ul personal secundar al utilizatorului nu a fost accesat.

## Conexiuni și chei

Conexiunile modulului 06 cer `php bin/keyring.php` după migrații. Cheile rămân locale, ignorate de Git; vezi [conexiuni, rotație și reluare pe alt PC](integrations.md). Testele folosesc exclusiv chei și conturi sintetice și nu au nevoie de keyring-ul aplicației.

## Surse

Alegerea ramurii LTS: [Symfony 7.4](https://symfony.com/releases/7.4). Compatibilitate teste: [PHPUnit supported versions](https://phpunit.de/supported-versions.html). Installerul este verificat conform [Composer](https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md). Runtime DB: [MySQL 8.4](https://dev.mysql.com/downloads/mysql/8.4.html) și [imaginea oficială Docker](https://hub.docker.com/_/mysql).
