# Starea curentă — Ordely

2026-09-29. Registrul: [plan.md](plan.md). **01–08 DONE. 09 PAUSED după 09.1; următorul pas este 09.2.** Nu există modul în implementare la această predare. 10–21 neîncepute.

## Modulul 08 închis

La cererea „Cloneaza ce e pe github si hai sa continuam”, clona existentă de pe PC-ul inițial a fost sincronizată prin fetch/pull fast-forward de la `807e0da` la `bea3206`, fără modificări locale pierdute. Conexiunea App Bridge autentică din 07 exista în această DB; refresh-ul real a reușit (versiune 4→5), cu exact read_customers/read_inventory/read_locations/read_orders/read_products. Blocajul de legare de pe celălalt PC nu se aplică acestei baze locale.

**Import și reimport persistat PASS**, prin API HTTP autentificat + worker real: fixture existentă ORDELY-TEST-M08, ID Shopify `8239905505617`, 30 linii în două pagini 25+5, total 3600 bani RON, încasat 0/restant 3600, date sintetice corecte. ID-ul comenzii și cele 30 ID-uri de linie, versiunile și hash-urile au rămas identice; un singur ORDER_IMPORTED. Catalog: 17 produse, 26 variante, 28 inventare, fără dubluri. Datele comenzii și staging-ul sunt criptate; publicarea rămâne completă în timpul reimportului, staging gol la final. Nu s-a recreat comanda și nu s-a acordat scriere. [Fișa 08](docs/modules/08-commerce-import.md), [dovezi și comenzi](docs/testing/08-commerce-import.md).

**207 teste PASS** pe acest PC: 106 unit/707 assertions, 101 integration/634 assertions, 213 lint, PHPStan 8, trei fișiere JS și 6 probe HTTP. Config Shopify validă. [CI 36486355856 Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36486355856) reverificat pentru ultimul cod `7e87d9e`; această sesiune modifică numai documentația versionată. Inspecția UI a comenzii nu s-a rerulat; detaliile și autentificarea au fost verificate prin HTTP real, separat de testele automate și de probele UI anterioare.

## Următorul pas

09.1 este implementat și publicat: ciorne independente de CMS, calcul exact, creare/editare/arhivare, revizii criptate, tenant/store și audit; cod `f622aab`, [CI PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). 09.2 nu a început: date complete de emitere, deciziile D01/D08, conexiunea/adaptorul Oblio. Emitere/storno/PDF rămân neimplementate. La reluare, reactivează numai 09 în plan, citește [fișa 09](docs/modules/09-invoicing.md), D01/D08/D16 și definește criteriile 09.2 înainte de cod. Clarifică regulile de facturare și contul/seriile înaintea probelor de emitere. Nu începe 10.

## Mediu și Git

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–007 aplicate în app/test, Composer 2.10.3, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. MySQL și preview-ul PHP/Shopify/tunel rămân pornite; panou [local](http://127.0.0.1:8080/). Workerul a terminat importurile și nu rulează permanent. Nu există scheduler/supervisor de producție. URL-ul tunelului se schimbă la restart.

Păstrează `.env`, `var/keys/keyring.json` și DB împreună; nu se transferă prin Git. Contul sintetic este în `var/dev-account.json`; proba HTTP și rezultatele sanitizate în `var/resume-08-*`, ignorate. Secretele/tokenurile nu sunt în documente sau Git. Pe un alt PC verifică legătura sa locală; nu presupune că DB sau conexiunea acestui PC au fost copiate.

Remote `https://github.com/razvanstav/ordely.git`, branch `codex/modul-01-arhitectura`. Commitul acestei predări se identifică din Git; push-ul și egalitatea HEAD/origin se verifică la finalul sesiunii. Retenția datelor reale, distribuția și aprobările de producție rămân înainte de pilot; probele dev nu le înlocuiesc.
