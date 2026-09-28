# Verificări 07 — Shopify

2026-09-28. Modul IN_PROGRESS. Pregătire mediu și validatori interni; integrarea reală nu este încă implementată.

Windows, PHP 8.4.24 portabil, Composer 2.10.3, MySQL 8.4.11. Prefix PowerShell: `$env:PATH=(Join-Path (Get-Location) 'var/tools/php-8.4.24')+';'+$env:PATH`.

| Comandă / scenariu | Rezultat | Dovadă / limitare |
| --- | --- | --- |
| `git pull --ff-only` | PASS | Baza 9982a70, deja actualizată |
| `./scripts/install-composer.ps1` | PASS | Installer SHA-384 verificat; Composer 2.10.3 |
| `./scripts/windows-mysql.ps1 -Action Start` | PASS | MySQL 8.4.11 în var/, separat de XAMPP |
| `php var/tools/composer.phar install --no-interaction --prefer-dist` | PASS | 31 pachete din lockfile, fără update |
| `php var/tools/composer.phar check` înainte de codul 07 | PASS | 166 lint, PHPStan 8, 73 unit/586 assertions, 56 integration/306 assertions |
| `php vendor/bin/phpunit --filter ShopifyAuthentication` | PASS | 20 teste, 43 assertions |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` | PASS | Level 8, fără erori după validatorii 07 |
| `php var/tools/composer.phar check` după codul 07 | PASS | 174 lint, PHPStan 8, 93 unit/629 assertions, 56 integration/306 assertions |
| Dev Dashboard → Stores | PASS | Niciun dev store existent; Ordely Shop creat și badge dev verificat |
| Dev Dashboard → Apps | PASS | Ordely, module-07-dev activă, zero scopes, neinstalată |
| `shopify version` cu Node 24.19.0 | PASS | CLI 4.8.2 |
| `shopify app config link --client-id 62da15c72a85d17d1ee0cf8d7fa5058d --file-name shopify.app.toml` | BLOCKED | Cere passkey-ul utilizatorului; configurația nu este încă descărcată |
| Token exchange/refresh, webhook și dezinstalare reale | NOT_RUN | Endpoint-uri neimplementate, aplicația neinstalată |

Cazuri locale: HS256 valid, expirare, nbf/iat în viitor, tipuri greșite, audiență/issuer greșite, alg none, semnătură invalidă, JWT critic nesuportat, bearer malformat/supradimensionat, domenii cu port/path/userinfo/sufix fals și shop diferit de allowlist. HMAC folosește body-ul brut; un byte schimbat invalidează semnătura. Debug/JSON/serializarea configurației nu expun secretul.

Testele nu confirmă App Bridge real și nu acordă implicit drepturi tenant/store unei identități Shopify. Acestea urmează în pasul 2.
