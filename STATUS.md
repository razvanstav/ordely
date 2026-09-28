# Starea curentă — Ordely

2026-09-29. Registrul: plan.md. **01–07 DONE; 08 REVIEW, unicul activ; 09 PAUSED după 09.1.** Utilizatorul a cerut reluarea probelor Shopify și a autorizat explicit CLI Connector App/write_orders și, ulterior, preview-ul Ordely cu write_orders temporar pentru o singură comandă sintetică.

CLI 4.8.2 instalat local în var/shopify-cli, autentificarea contului și store auth pe ordely-shop.myshopify.com reușite. .env configurat prin app env pull; allowlist dev setat. Lookup-ul fixture are zero rezultate. orderCreate prin CLI a fost respins de Shopify deoarece tokenul este online; nu s-a creat comanda. Preview-ul Ordely este pornit cu scope temporar write_orders. Așteptăm legarea reală prin App Bridge, apoi creare unică folosind tokenul offline, retragerea write_orders și import/reimport. [Raport 08](docs/testing/08-commerce-import.md).

Ultimele probe locale: 58 teste Shopify/import, 286 assertions și 6 verificări HTTP PASS. Controlul browserului este indisponibil din cauza erorii sandbox ACL; legarea este singurul pas manual în așteptare. Acordurile necesare sunt deja primite (D17).

## Continuare

09.1 este implementat, testat și publicat: ciorne independente de CMS, calcul exact pe server, creare/editare/arhivare, revizii criptate, autorizare merchant/store și audit. 207 teste locale PASS (106 unit/707 assertions, 101 integration/634 assertions), 213 lint, PHPStan 8, JS și 6 probe HTTP. Cod `f622aab39b8ff4791bb4c6cf300471b860ceb4c6`, push și hash remote verificate, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). [Ghid](docs/invoicing.md), [raport](docs/testing/09-invoicing.md).

După închiderea probei 08, următorul pas al facturării este 09.2: date complete de emitere, deciziile D01/D08 și conexiunea/adaptorul Oblio. 09 este PAUSED în timpul reluării 08; emitere/storno/PDF sunt încă neimplementate. Nu începem 10. Inspecția vizuală manuală a UI 09.1 este NOT_RUN, distinctă de fluxul HTTP real verificat.

## Dovezi existente și mediu local

Codul 08 și predarea de pe celălalt PC au fost aduse la 807e0da, urmate de predarea acestui PC 52aa623. Local: 204 lint, PHPStan 8, 101 unit/677 assertions, 89 integration/512 assertions, JS și 6 probe HTTP PASS. CI pentru codul e8a4133: [Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36420254697). Catalogul real testat anterior: 17 produse, 26 variante, 28 poziții de stoc, trei importuri fără dubluri; proba comenzii reale nu s-a executat.

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–007 app/test. Preview-ul standalone anterior a fost înlocuit de Shopify CLI dev: PHP local 8080 și tunel HTTPS. .env/keyring rămân locale și ignorate. Cont sintetic în var/dev-account.json; cod de legare în var/shopify-link-code.txt. shopify.app.toml are numai local write_orders temporar, de retras după fixture; nu publica această modificare. Git transferă codul și predarea, nu DB/tokenurile/cheile/helper-ele din var.

Remote https://github.com/razvanstav/ordely.git; branch codex/modul-01-arhitectura. Înainte de lucru pe alt PC: fetch/pull, apoi AGENTS, STATUS, plan, fișa modulului activ și deciziile. Un singur modul activ; testele automate offline rămân obligatorii.
