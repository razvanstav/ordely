# Starea curentă — Ordely

2026-09-29. Registrul: plan.md. **01–07 DONE; 08 REVIEW, unicul activ; 09 PAUSED după 09.1.** Utilizatorul a autorizat continuarea autonomă și accesul necesar numai pe Ordely dev, apoi închiderea mediului dacă restanța nu poate fi rezolvată fără el.

Comanda sintetică **ORDELY-TEST-M08 există**, ID Shopify `8239905505617`, creată o singură dată prin autentificarea oficială client_credentials a aplicației Ordely. 30 linii, test=true, PENDING, 36,00 RON, fără plăți/notificări/modificări de stoc. **Nu o recrea și nu readăuga write_orders.** Query-urile reale și ImportNormalizer au trecut de două ori: pagini 25+5, sume exacte, email/adresă sintetice corecte, conținut stabil și criptare OrderCipher. Aceste probe nu sunt import/reimport persistat prin worker. [Raport 08](docs/testing/08-commerce-import.md).

Proba reală a descoperit lipsa read_customers pentru order.customer.id; shopify.app.toml este corectat cu cinci scope-uri exclusiv de citire. write_orders retras și absența sa verificată prin API inclusiv după oprirea preview-ului. Ultimele probe locale: config Shopify validă, 58 teste/286 assertions și 6 verificări HTTP PASS. Conexiunea App Bridge locală este încă absentă; controlul browserului nu pornește. Importul/reimportul DB cu ID-uri/versiuni/evenimente stabile rămâne NOT_RUN. Acordurile sunt primite; blocajul este tehnic (D17).

Corecția și raportul publicate în `7e87d9e3364977ad4d3886b97920ff3637e6b117`, hash remote verificat. [CI 36486355856 PASS pe Windows și Linux](https://github.com/razvanstav/ordely/actions/runs/36486355856). Predarea ulterioară adaugă numai rezultatul CI în documente.

## Continuare

09.1 este implementat, testat și publicat: ciorne independente de CMS, calcul exact pe server, creare/editare/arhivare, revizii criptate, autorizare merchant/store și audit. 207 teste locale PASS (106 unit/707 assertions, 101 integration/634 assertions), 213 lint, PHPStan 8, JS și 6 probe HTTP. Cod `f622aab39b8ff4791bb4c6cf300471b860ceb4c6`, push și hash remote verificate, [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). [Ghid](docs/invoicing.md), [raport](docs/testing/09-invoicing.md).

După închiderea probei 08, următorul pas al facturării este 09.2: date complete de emitere, deciziile D01/D08 și conexiunea/adaptorul Oblio. 09 este PAUSED în timpul reluării 08; emitere/storno/PDF sunt încă neimplementate. Nu începem 10. Inspecția vizuală manuală a UI 09.1 este NOT_RUN, distinctă de fluxul HTTP real verificat.

## Dovezi existente și mediu local

Codul 08 și predarea de pe celălalt PC au fost aduse la 807e0da, urmate de predarea acestui PC 52aa623. Local: 204 lint, PHPStan 8, 101 unit/677 assertions, 89 integration/512 assertions, JS și 6 probe HTTP PASS. CI pentru codul e8a4133: [Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36420254697). Catalogul real testat anterior: 17 produse, 26 variante, 28 poziții de stoc, trei importuri fără dubluri; proba comenzii reale nu s-a executat.

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–007 app/test. PHP cURL nu avea CA; reparat cu bundle-ul Mozilla/curl verificat SHA-256 și configurat în php.ini, fără relaxarea TLS; [setup reproductibil](docs/setup.md). .env/keyring și contul var/dev-account.json rămân locale. Tokenul client_credentials este criptat local, expiră după aproximativ 24h și nu este o conexiune App Bridge. Git nu transferă DB/tokenuri/chei/helper-e.

**Mediu închis:** CLI/PHP/tunel oprite, app dev clean reușit pe Ordely dev, MySQL oprit cu datele păstrate. La reluare: pornește MySQL și preview-ul cu configurația actuală, regenerează codul de legare (cel vechi a expirat), conectează autentic App Bridge; apoi importă/reimportă fixture-ul existent prin UI/worker și compară ID-urile, versiunile, numărătorile, criptarea DB și ORDER_IMPORTED. Nu mai este necesară crearea unei comenzi. Nu începe 09.2 înainte de închiderea sau reamânarea explicită a 08.

Remote https://github.com/razvanstav/ordely.git; branch codex/modul-01-arhitectura. Înainte de lucru pe alt PC: fetch/pull, apoi AGENTS, STATUS, plan, fișa modulului activ și deciziile. Un singur modul activ; testele automate offline rămân obligatorii.
