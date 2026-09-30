# Starea curentă — Ordely

2026-09-30. Registrul: [plan.md](plan.md). **01–08 DONE. 09 IN_PROGRESS, unicul activ. Interfața existentă este reorganizată la cererea utilizatorului; 09.2b Oblio rămâne implementat local, cu proba reală NOT_RUN.** 09.1 este păstrat; 10–21 neîncepute.

## Acces local disponibil

2026-09-30: la cererea explicită a utilizatorului, contul său local a fost creat ca owner în spațiul de dezvoltare existent. Parola este stocată numai ca hash; emailul și parola sunt omise din predare/Git. Login real în Chrome PASS, magazinul și conexiunea Shopify păstrate; formularul Integrări → Oblio este deschis, cu câmpurile de cont goale. Se completează emailul și cheia API Oblio direct acolo, apoi asociere magazin și citirea firmelor/seriilor/TVA. Parola Ordely nu este cheia API Oblio.

Setarea administrativă a parolei alese este o excepție punctuală de dezvoltare (D20); validatorul standard de minimum 12 bytes nu este modificat. Înainte de deployment este necesară o parolă conformă. Nu s-a configurat hosting sau o publicare online nouă; Git privat nu este hosting și nu transferă DB, conturi sau secrete. [Probe](docs/testing/09-invoicing.md).

Utilizatorul a ales explicit continuarea locală; nu are hosting de configurat acum. Următorul pas rămâne salvarea datelor API Oblio în formularul deschis.

## Interfață simplificată

La cererea de grupare și design mai clar, panoul unic a fost împărțit în Acasă, Comenzi și catalog, Facturare, Magazine, Integrări și Activitate sistem. Acasă arată numărătorile locale și pașii de configurare; formularele apar la alegerea serviciului, iar întreținerea și diagnosticul sunt în detalii extensibile. Design verde discret, navigare cu URL/back/focus, aranjare adaptată telefonului. Este o extindere explicită în 09 a suprafețelor deja existente (D19); modulul 20 rămâne PLANNED.

Verificări noi PASS: toate cele șase pagini în Chrome, desktop și viewport 390×844 fără overflow, navigare/tastatură, formulare Shopify/Oblio, calcul sintetic de ciornă 12.00 RON fără salvare, filtrul de activitate și consola fără erori. Suita locală rerulată: 220 teste, 222 lint, PHPStan 8, două sintaxe JS și 6 probe HTTP PASS. [Scenarii și limite](docs/testing/09-invoicing.md). Preview-ul rămâne deschis la [Acasă](http://127.0.0.1:8080/#overview), cu dimensiunea normală a browserului restaurată.

## Modulul 08 închis

La cererea „Cloneaza ce e pe github si hai sa continuam”, clona existentă de pe PC-ul inițial a fost sincronizată prin fetch/pull fast-forward de la `807e0da` la `bea3206`, fără modificări locale pierdute. Conexiunea App Bridge autentică din 07 exista în această DB; refresh-ul real a reușit (versiune 4→5), cu exact read_customers/read_inventory/read_locations/read_orders/read_products. Blocajul de legare de pe celălalt PC nu se aplică acestei baze locale.

**Import și reimport persistat PASS**, prin API HTTP autentificat + worker real: fixture existentă ORDELY-TEST-M08, ID Shopify `8239905505617`, 30 linii în două pagini 25+5, total 3600 bani RON, încasat 0/restant 3600, date sintetice corecte. ID-ul comenzii și cele 30 ID-uri de linie, versiunile și hash-urile au rămas identice; un singur ORDER_IMPORTED. Catalog: 17 produse, 26 variante, 28 inventare, fără dubluri. Datele comenzii și staging-ul sunt criptate; publicarea rămâne completă în timpul reimportului, staging gol la final. Nu s-a recreat comanda și nu s-a acordat scriere. [Fișa 08](docs/modules/08-commerce-import.md), [dovezi și comenzi](docs/testing/08-commerce-import.md).

**207 teste PASS** pe acest PC: 106 unit/707 assertions, 101 integration/634 assertions, 213 lint, PHPStan 8, trei fișiere JS și 6 probe HTTP. Config Shopify validă. [CI 36486355856 Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36486355856) reverificat pentru ultimul cod `7e87d9e`; această sesiune modifică numai documentația versionată. Inspecția UI a comenzii nu s-a rerulat; detaliile și autentificarea au fost verificate prin HTTP real, separat de testele automate și de probele UI anterioare.

## Următorul pas

Utilizatorul confirmă contul Oblio și autorizează pregătirea locală. 09.2b este prioritar față de 09.2a: formular email/cheie API, criptare și binding existente, port Core neutru, adaptor cu autentificare și trei citiri, endpoint cu CSRF/roluri/merchant/store și revalidare după rețea. UI separă conexiunea salvată de accesul verificat și arată firme/serii de factură/TVA la cerere. Niciun document nu poate fi emis de acest adaptor. [Operare și limite](docs/oblio-integration.md).

**220 teste locale PASS**: 112 unit/768 assertions și 108 integration/664 assertions; 222 lint, PHPStan 8, JavaScript și 6 probe HTTP PASS. Browserul furnizează valori mascate la citirea câmpurilor de cont Oblio; încercarea anterioară de transfer a fost oprită de validarea emailului, fără conexiune creată. Câmpurile sunt goale; după reorganizare formularul se deschide din Acasă → Configurează sau Integrări → Oblio. Proba reală firme/serii/TVA: **NOT_RUN**. Detalii în [raport](docs/testing/09-invoicing.md).

Adaptorul `11dddf4` este publicat, cu [CI Windows/Linux 36551856411 PASS](https://github.com/razvanstav/ordely/actions/runs/36551856411). Reorganizarea UI `b1709f0` este publicată, hash local/remote identic verificat și [CI Windows/Linux 36575021341 PASS](https://github.com/razvanstav/ordely/actions/runs/36575021341). Predarea ulterioară consemnează numai verificările, fără alt cod runtime.

Reluare exactă: contul personal Ordely este autentificat pe acest PC, iar Integrări → Oblio este deschis. Verifică dacă utilizatorul a salvat conexiunea; după salvarea directă, asociaz-o magazinului local și verifică numai citirea firmelor/seriilor/TVA, fără date reale în raport. Apoi 09.2a: datele complete/profilul de emitere și politica D01. Firma/seria de teste și setările stoc/email/SPV nu sunt stabilite; D08 rămâne deschis pentru emitere. Nu se cer chei în chat.

09.1 este implementat și publicat: ciorne independente de CMS, calcul exact, creare/editare/arhivare, revizii criptate, tenant/store și audit; cod `f622aab`, [CI PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). 09.2a/c și 09.3 rămân neimplementate: date complete de emitere, maparea facturii, emitere/storno/PDF. La reluare continuă numai 09; citește [fișa 09](docs/modules/09-invoicing.md), D01/D08/D16/D18. Nu începe 10.

## Mediu și Git

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–007 aplicate în app/test, Composer 2.10.3, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. MySQL și preview-ul PHP/Shopify/tunel rămân pornite; panou [local](http://127.0.0.1:8080/). Workerul a terminat importurile și nu rulează permanent. Nu există scheduler/supervisor de producție. URL-ul tunelului se schimbă la restart.

Păstrează `.env`, `var/keys/keyring.json` și DB împreună; nu se transferă prin Git. Contul sintetic este în `var/dev-account.json`; proba HTTP și rezultatele sanitizate în `var/resume-08-*`, ignorate. Secretele/tokenurile nu sunt în documente sau Git. Pe un alt PC verifică legătura sa locală; nu presupune că DB sau conexiunea acestui PC au fost copiate.

Remote `https://github.com/razvanstav/ordely.git`, branch `codex/modul-01-arhitectura`. Commitul acestei predări se identifică din Git; push-ul și egalitatea HEAD/origin se verifică la finalul sesiunii. Retenția datelor reale, distribuția și aprobările de producție rămân înainte de pilot; probele dev nu le înlocuiesc.
