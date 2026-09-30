# Starea curentă — Ordely

Publicare schelet D25: cod `27f544a` sincronizat pe `codex/modul-01-arhitectura`; push/fetch PASS, HEAD/origin identice și checkout curat verificate. Verificările locale ale lotului au trecut; CI este declanșat automat la push și nu este declarat PASS fără rezultat. Nu s-au adăugat teste noi pentru ecranele de structură. Această predare modifică numai documentația.


## Scheletul întregii aplicații — 09-S livrat

2026-09-30: la cererea explicită a utilizatorului, prioritatea este structura întregii aplicații (D25), înaintea aprofundării facturării. Livrate 9 ecrane noi și navigare: expediții, tracking, retururi, refuzuri, schimburi, retrimiteri, rambursări, administrare portal și setări. Registru/componente comune, filtre, pași, câmpuri și legături între fluxuri; Acasă arată harta aplicației. Contextul magazinelor vine din sesiunea existentă; acțiunile viitoare rămân dezactivate, fără date fabricate sau salvări simulate. Folderele Shipping/Returns/Exchanges/Refunds/Portal sunt pregătite pe patru straturi; contractele Core sunt păstrate.

Verificat: sintaxe workspace.js/app.js/invoicing.js, HTTP 6 probe și o singură regresie existentă PASS (246 teste, 238 lint, PHPStan 8). Niciun test nou pentru ecranele de structură. Chrome owner: toate cele 9 rute, reload, legătură Schimburi → Retururi și Back, desktop/mobil 390×844 fără overflow, câmpuri/acțiuni dezactivate și consola fără erori. Afișarea hărții Acasă a fost corectată după proba inițială; verificarea finală confirmă numai ecranul activ. Screenshot local ignorat. Probe provider și autorizarea vizuală a altor roluri NOT_RUN în acest lot.

Reluare: structura transversală este gata; conectăm fluxurile reale în loturi coerente, fără o conversație/commit pentru fiecare detaliu de UI. Facturarea existentă rămâne disponibilă; restanțele 09.2a/c/09.3 se păstrează. 09 rămâne singurul IN_PROGRESS; 10–21 PLANNED pentru implementarea funcțiilor, cu structura pregătită. Nu se consideră modulele terminate din existența ecranelor. Stoc management rămâne separat/amânat. Publicarea se verifică la finalul lotului.


Publicare 09.2a.2.2a: cod `4350a86` trimis pe `codex/modul-01-arhitectura`; push, fetch și HEAD/origin identice verificate, checkout curat. [CI Windows/Linux 36707834906 PASS](https://github.com/razvanstav/ordely/actions/runs/36707834906), `gh run watch 36707834906 --exit-status --interval 15` exit 0: PHP/MySQL pe ambele sisteme, migrare/restart Windows și HTTP Linux. Regresie locală 246 teste, 238 lint, PHPStan 8, două sintaxe JS și 6 HTTP PASS. Linkuri/diff/staged scanner pentru secrete/date fiscale PASS. Captura nativă finală `var/ui-order-draft-20260930.jpg` este locală și ignorată. Această predare ulterioară modifică numai documentația; emitere/storno/PDF rămân NOT_RUN.


## Ciornă din comandă CMS — 09.2a.2.2a implementat

2026-09-30: modulul 09 rămâne unicul IN_PROGRESS. Comenzi → Pregătește facturarea → Salvează ciorna din comandă păstrează local raportul CMS inclusiv incomplet, separat de ciornele manuale. Redeschiderea arată snapshot-ul salvat; schimbările comenzii/profilului/conexiunii sunt semnalate, actualizarea și salvarea sunt explicite. Serverul recitește sursa, verifică versiunile și accesul; criptare/AAD, CAS/retry, audit atomic și inventar chei. Migrația 009 aplicată în app/test. Propunerea inițială se elimină la ștergerea sursei CMS; nu este registrul viitoarelor documente fiscale (D24).

PASS local: 246 teste (119 unit/826 assertions, 127 integration/839), 238 lint, PHPStan 8, două sintaxe JS și 6 HTTP. Două procese concurente, rollback, privacy, profil invalidat, roluri/granturi/CSRF/izolare și criptare testate. Chrome: salvare a comenzii sintetice existente, 30 linii/36.00 RON, reload/redeschidere și resalvare fără dubluri (revizia 1), Enter/focus, filtrare cu ștergerea datelor, desktop/mobil 390×844 fără overflow, consola fără erori. [Probe](docs/testing/09-invoicing.md).

Reluare exactă: Facturare are o ciornă din comanda de test, salvată local. Continuă 09.2a.2.2b: completările fiscale persistate (emitent, destinatar, adrese, unități/tratamente TVA, date document) și snapshot-ul fiscal neutru. Lipsurile comenzii de test nu cer completare manuală acum; datele disponibile vor veni din Shopify. Firma/seria emitentului rămân din profilul Oblio. canIssue=false; emiterea/storno/PDF și mediul D08 sunt NOT_RUN/restante. 10–21 rămân PLANNED. Publicarea Git și CI se consemnează după verificare.


Publicare 09.2a.2.1: cod `6556a4f` trimis pe branch-ul proiectului, HEAD/origin identice și checkout curat verificate. [CI Windows/Linux 36701852570 PASS](https://github.com/razvanstav/ordely/actions/runs/36701852570), urmărit cu `gh run watch 36701852570 --exit-status --interval 10` până la exit 0. Regresie locală: 240 teste, 232 lint, PHPStan 8, două sintaxe JS și 6 probe HTTP PASS. Linkuri relative, diff/staged check și scanner staged pentru secrete/date fiscale PASS. Această predare ulterioară modifică numai documentația; nu pretinde o nouă probă de emitere.

## Pregătire facturare din CMS — 09.2a.2.1

2026-09-30: subpas implementat, 09 rămâne unicul IN_PROGRESS. Din Comenzi → „Pregătește facturarea” se deschide în Facturare o vedere numai de citire: client/adresă de facturare, firmă/serie locală, sume exacte și toate liniile importate, cu lipsurile explicite. Nu se înlocuiește adresa de facturare cu livrarea, nu se deduce cota TVA sau tipul clientului. Prețurile cu/fără taxe, reducerile, cantitățile originale/curente și taxele rămân distincte. Conexiunea schimbată cere reverificare; API și repository validează merchant/store/rol/granturi. Nicio emitere ori scriere în CMS/stoc; ciornele existente nu se modifică.

Probe: 9 teste noi MySQL/HTTP/unitare PASS, PHPStan 8 și Chrome desktop/mobil 390×844 PASS (30 linii, lipsuri grupate, fără overflow, Enter/închidere/focus, date eliminate la închidere). Regresia finală și publicarea se consemnează în [raport](docs/testing/09-invoicing.md). Capturi și date reale nu se publică. Conexiunea Oblio existentă este păstrată, fără apel extern nou.

Reluare exactă: vederea comenzii este deschisă în Facturare. Urmează 09.2a.2.2 — completările persistate criptat și snapshot-ul fiscal (emitent/destinatar, adresă, unități/tratamente TVA, date document), legate de versiunea comenzii și de profil; apoi 09.2c/09.3. Vederea actuală este întotdeauna INCOMPLETE/canIssue=false, nu o ciornă nouă și nu o validare fiscală. Mediul D08 rămâne de confirmat înainte de emitere. 10–21 rămân PLANNED.

## Reguli acceptate — 2026-09-30

D01 — reguli de produs acceptate: diferența de preț la schimb se introduce manual, fără compensare automată. Bifa Transport preia tariful configurat în site și facturează pe datele inițiale ale clientului; AWB se generează automat cu COD egal cu diferența manuală plus transportul selectat. Nimic/total zero la schimb sau retrimitere înseamnă fără factură nouă/COD 0; regula nu elimină factura unei comenzi obișnuite deja plătite.

Storno automat numai la înregistrarea unui refuz de primire, pe original verificat; returul obișnuit nu declanșează storno. Facturile externe se acceptă numai după identificare/verificare în Oblio; original neverificabil blochează storno. Refuzul repetat nu dublează storno; rezultat necunoscut cere reconciliere conform D11. Planul nu avea un modul distinct pentru refuzuri: fluxul este păstrat în backlog-ul 16, corelat cu tracking/recepție, fără implementarea lui acum.

D04 — gestiunea stocului, reintegrarea, ajustările și rezervările sunt amânate explicit pentru o zonă separată. Oblio va emite fără modificări de stoc; citirea disponibilității rămâne separată. Email și SPV fără trimitere automată în etapa de test, acceptat. Mediul de emitere D08, validarea fiscală și probele reale de emitere/storno rămân restante. Aceste decizii actualizează propunerile istorice de mai jos; nu certifică providerul.

Reluare: 09.2a.2 — date complete și lista lipsurilor; 09 rămâne singurul modul activ. Schimbare numai documentară; fără emitere sau teste runtime rerulate.


2026-09-30. Registrul: [plan.md](plan.md). **01–08 DONE. 09 IN_PROGRESS, unicul activ. 09.2a.1 finalizat: firma și seria verificate sunt salvate criptat pentru magazin și afișate în Facturare.** 09.1, conexiunea/citirea reală 09.2b și UI D21 sunt păstrate; 10–21 neîncepute.

## Configurare facturare salvată

Oblio are trei pași: verifică accesul, alege firma/citește datele, alege explicit seria și salvează. API-ul reverifică nomenclatoarele și contextul înaintea persistării; un profil per merchant/store, criptare cu AAD, CAS/retry fără dubluri, audit fără date fiscale și inventar de chei. Schimbarea conexiunii cere reverificarea profilului. Owner/admin cu acces la toate magazinele configurează; rolurile care pot citi facturarea văd numai magazinele permise. D22, migrația 008 în app/test.

PASS: 231 teste (112 unit/768 assertions, 119 integration/750), 226 lint, PHPStan 8, două sintaxe JS și 6 HTTP. Două procese reale pentru CAS/retry, rollback al profilului și auditului, izolare/roluri, criptare și erori provider testate. Proba reală prin Chrome: singura firmă/serie din conexiunea existentă salvate local, rezumat păstrat după reload; desktop/mobil 390×844 fără overflow, Back/focus PASS, fără erori de aplicație în consolă. O configurație în inventarul cheilor, fără date reale în Git/predare. [Raport](docs/testing/09-invoicing.md).

Reluare exactă: [Facturare](http://127.0.0.1:8080/#invoicing) este deschisă și arată profilul salvat; nu cere din nou cheia și nu recrea conexiunea. Continuă 09.2a.2: date complete ale emitentului/destinatarului, adresă structurată și linii/TVA explicit, apoi lista lipsurilor unei facturi. Profilul actual conține numai firmă/serie; nu este snapshot fiscal complet. D01/D08 pentru emitere și 09.2c/09.3 rămân restante; nu s-a emis nimic.

Cod `45ac9ba`, corecție de test `1fcf8e4`, publicate pe branch-ul proiectului; hash local/origin identic și checkout curat verificate. [CI Windows/Linux 36687850355 PASS](https://github.com/razvanstav/ordely/actions/runs/36687850355), urmărit până la exit 0. Prima rulare Linux a refuzat triggerul privilegiat din test; proba de rollback a fost corectată fără schimbarea permisiunilor DB. Scanarea staged exclude și datele fiscale ale profilului, linkuri/diff check PASS. Predarea ulterioară consemnează numai rezultatul CI.

## Integrări organizate pe activitate

Catalogul separă Facturare (Oblio), Curierat (Sameday/FAN în pregătire) și Magazine online (Shopify). Apăsarea pe Oblio deschide ecranul său la `#integrations/oblio`, cu magazinul asociat, pași de verificare a accesului/alegere a firmei și liste distincte pentru serii/TVA. Text mai mare, formulare spațiate și administrarea închisă implicit. Contul existent se gestionează direct; un cont suplimentar se adaugă numai la cerere. Simulatoarele nu mai apar în formularul Oblio. Modulele 10/11/20 rămân neîncepute.

PASS: browser desktop și 390×844 fără overflow pentru catalog/date/formular de acces, Back/reload/Enter/focus, câmpuri sintetice șterse la închidere, separare Shopify/Oblio, citire reală 1 firmă/1 serie/10 cote, consola fără erori. 220 teste, 222 lint, PHPStan 8, două sintaxe JS și 6 HTTP PASS. Preview-ul PHP/MySQL a fost repornit după refuzul conexiunii locale; Chrome funcționează acum. [Probe și limite](docs/testing/09-invoicing.md). Ecranul [Oblio](http://127.0.0.1:8080/#integrations/oblio) rămâne deschis; nu s-au modificat credentiale sau emis documente.

Cod UI `a357c81` publicat, push și HEAD/origin identice verificate; [CI Windows/Linux 36681823310 PASS](https://github.com/razvanstav/ordely/actions/runs/36681823310). Predarea ulterioară consemnează numai acest rezultat, fără alt cod runtime.

## Oblio conectat și verificat

Utilizatorul a furnizat datele API și a confirmat emailul contului. Conexiunea a fost salvată prin UI și asociată ca implicită magazinului existent. Citirea reală în Chrome PASS: 1 firmă, 1 serie de factură și 10 cote TVA. Verificarea DB locală confirmă o singură conexiune activă, versiunea 2, credentiale criptate și două citiri auditate numai cu versiune/număr de rezultate. Datele de cont și ale firmei nu sunt incluse în predare/Git. [Probe](docs/testing/09-invoicing.md).

La proba inițială de citire nu exista un profil salvat. Acum firma/seria sunt persistate conform 09.2a.1 de mai sus; mediul probelor fiscale, D01 și opțiunile stoc/email/SPV rămân de stabilit. Nu s-au emis documente. Blocajul extensiei din proba inițială nu s-a repetat în verificările UI ulterioare.

## Acces local disponibil

2026-09-30: la cererea explicită a utilizatorului, contul său local a fost creat ca owner în spațiul de dezvoltare existent. Parola este stocată numai ca hash; emailul și parola sunt omise din predare/Git. Login real în Chrome PASS, magazinul și conexiunea Shopify păstrate. Ulterior, conexiunea Oblio a fost salvată și verificată conform probei de mai sus. Parola Ordely nu este cheia API Oblio.

Setarea administrativă a parolei alese este o excepție punctuală de dezvoltare (D20); validatorul standard de minimum 12 bytes nu este modificat. Înainte de deployment este necesară o parolă conformă. Nu s-a configurat hosting sau o publicare online nouă; Git privat nu este hosting și nu transferă DB, conturi sau secrete. [Probe](docs/testing/09-invoicing.md).

Utilizatorul a ales explicit continuarea locală; nu are hosting de configurat acum. Următorul pas este profilul de emitere, folosind conexiunea deja salvată.

## Interfață simplificată

La cererea de grupare și design mai clar, panoul unic a fost împărțit în Acasă, Comenzi și catalog, Facturare, Magazine, Integrări și Activitate sistem. Acasă arată numărătorile locale și pașii de configurare; formularele apar la alegerea serviciului, iar întreținerea și diagnosticul sunt în detalii extensibile. Design verde discret, navigare cu URL/back/focus, aranjare adaptată telefonului. Este o extindere explicită în 09 a suprafețelor deja existente (D19); modulul 20 rămâne PLANNED.

Verificări UI din 2026-09-29 PASS: toate cele șase pagini în Chrome, desktop și viewport 390×844 fără overflow, navigare/tastatură, formulare Shopify/Oblio, calcul sintetic de ciornă 12.00 RON fără salvare, filtrul de activitate și consola fără erori. Suita locală rerulată atunci: 220 teste, 222 lint, PHPStan 8, două sintaxe JS și 6 probe HTTP PASS. [Scenarii și limite](docs/testing/09-invoicing.md). Preview-ul este disponibil la [Integrări](http://127.0.0.1:8080/#integrations).

## Modulul 08 închis

La cererea „Cloneaza ce e pe github si hai sa continuam”, clona existentă de pe PC-ul inițial a fost sincronizată prin fetch/pull fast-forward de la `807e0da` la `bea3206`, fără modificări locale pierdute. Conexiunea App Bridge autentică din 07 exista în această DB; refresh-ul real a reușit (versiune 4→5), cu exact read_customers/read_inventory/read_locations/read_orders/read_products. Blocajul de legare de pe celălalt PC nu se aplică acestei baze locale.

**Import și reimport persistat PASS**, prin API HTTP autentificat + worker real: fixture existentă ORDELY-TEST-M08, ID Shopify `8239905505617`, 30 linii în două pagini 25+5, total 3600 bani RON, încasat 0/restant 3600, date sintetice corecte. ID-ul comenzii și cele 30 ID-uri de linie, versiunile și hash-urile au rămas identice; un singur ORDER_IMPORTED. Catalog: 17 produse, 26 variante, 28 inventare, fără dubluri. Datele comenzii și staging-ul sunt criptate; publicarea rămâne completă în timpul reimportului, staging gol la final. Nu s-a recreat comanda și nu s-a acordat scriere. [Fișa 08](docs/modules/08-commerce-import.md), [dovezi și comenzi](docs/testing/08-commerce-import.md).

**207 teste PASS** pe acest PC: 106 unit/707 assertions, 101 integration/634 assertions, 213 lint, PHPStan 8, trei fișiere JS și 6 probe HTTP. Config Shopify validă. [CI 36486355856 Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36486355856) reverificat pentru ultimul cod `7e87d9e`; această sesiune modifică numai documentația versionată. Inspecția UI a comenzii nu s-a rerulat; detaliile și autentificarea au fost verificate prin HTTP real, separat de testele automate și de probele UI anterioare.

## Următorul pas

09.2b a fost realizat înaintea 09.2a: formular email/cheie API, criptare și binding existente, port Core neutru, adaptor cu autentificare și trei citiri, endpoint cu CSRF/roluri/merchant/store și revalidare după rețea. UI separă conexiunea salvată de accesul verificat și arată firme/serii de factură/TVA la cerere. Urmează modelul complet și profilul de emitere; adaptorul actual nu poate emite documente. [Operare și limite](docs/oblio-integration.md).

**220 teste locale PASS la implementare**: 112 unit/768 assertions și 108 integration/664 assertions; 222 lint, PHPStan 8, JavaScript și 6 probe HTTP PASS. Încercarea inițială cu valori mascate nu a creat o conexiune; după furnizarea datelor de către utilizator, proba reală firme/serii/TVA este **PASS, 2026-09-30**. Proba inițială de conectare a schimbat numai configurația locală și documentația; modificarea UI ulterioară și regresia rerulată sunt consemnate în secțiunea de sus. Detalii în [raport](docs/testing/09-invoicing.md).

Adaptorul `11dddf4` este publicat, cu [CI Windows/Linux 36551856411 PASS](https://github.com/razvanstav/ordely/actions/runs/36551856411). Reorganizarea UI `b1709f0` este publicată, hash local/remote identic verificat și [CI Windows/Linux 36575021341 PASS](https://github.com/razvanstav/ordely/actions/runs/36575021341). Predarea ulterioară consemnează numai verificările, fără alt cod runtime.

Reluare exactă: verifică existența conexiunii Oblio active și asociate magazinului pe acest PC; nu crea o dublură și nu cere din nou cheia. Ecranul dedicat se deschide din Integrări → Facturare → Oblio și permite citiri la cerere. Continuă 09.2a: datele complete/profilul de emitere și politica D01. Firma/seria de teste și setările stoc/email/SPV nu sunt stabilite; D08 este verificat pentru citirea contului, dar rămâne deschis pentru emitere. Blocajul extensiei din proba precedentă nu s-a repetat în verificarea UI curentă. Pe alt PC nu presupune că DB/conexiunea au venit prin Git.

09.1 este implementat și publicat: ciorne independente de CMS, calcul exact, creare/editare/arhivare, revizii criptate, tenant/store și audit; cod `f622aab`, [CI PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). 09.2a.1 este finalizat conform secțiunii de sus. Restul 09.2a, 09.2c și 09.3 rămân: date complete de emitere, maparea facturii, emitere/storno/PDF. Continuă numai 09; citește [fișa 09](docs/modules/09-invoicing.md), D01/D08/D16/D18/D22. Nu începe 10.

## Mediu și Git

PHP 8.4.24, MySQL 8.4.11 pe 33060, migrații 001–008 aplicate în app/test, Composer 2.10.3, Node 24.19.0 din runtime Codex, Shopify CLI 4.8.2. MySQL și preview-ul PHP rămân pornite; panou [local](http://127.0.0.1:8080/). Workerul a terminat importurile și nu rulează permanent. Nu există scheduler/supervisor de producție. URL-ul tunelului se schimbă la restart.

Păstrează `.env`, `var/keys/keyring.json` și DB împreună; nu se transferă prin Git. Contul sintetic este în `var/dev-account.json`; proba HTTP și rezultatele sanitizate în `var/resume-08-*`, ignorate. Secretele/tokenurile nu sunt în documente sau Git. Pe un alt PC verifică legătura sa locală; nu presupune că DB sau conexiunea acestui PC au fost copiate.

Remote `https://github.com/razvanstav/ordely.git`, branch `codex/modul-01-arhitectura`. Commitul acestei predări se identifică din Git; push-ul și egalitatea HEAD/origin se verifică la finalul sesiunii. Retenția datelor reale, distribuția și aprobările de producție rămân înainte de pilot; probele dev nu le înlocuiesc.
