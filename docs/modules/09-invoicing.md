# Modul 09 — Facturare

## Lot activ 09.3a — intenție durabilă de emitere

Publicare `09fd659` pe codex/modul-01-arhitectura: push/fetch, egalitatea HEAD/origin și checkout curat PASS. CI nou neconfirmat. Predarea ulterioară modifică numai documentația.

Rezultat local: criteriile 09.3a îndeplinite. Migrația 010, IssueCipher/IssueIntents și API pregătire/citire, audit/permisiune invoices.issue și inventar chei; nu există rută/job/provider fiscal activ. Reutilizat D11 pentru retry/UNKNOWN/reconciliere fără modificarea coordonatorului. 7 teste noi/82 assertions și regresie PASS 264 teste/255 lint/PHPStan 8/6 HTTP. UI nemodificat, conturi/sursa reală neatinse. [Probe](../testing/09-invoicing.md), D28.

Reluare exactă 09.3b: anulare/replanificare explicită numai pentru intenție netrimisă, protejată față de rezervarea concurentă; apoi răspuns/document fiscal și transportul local în adaptor, înainte de activarea UI. Original/storno/PDF și extinderile fiscale D27 rămân în 09; probe reale la final D26. Text de reluare: „Continuă 09.3b de la IssueIntents și D28. Completează anularea sigură a intenției netrimise și integrarea locală a rezultatului fiscal, fără apel în contul real; nu înlocui snapshot folosit/UNKNOWN.” 09 unic IN_PROGRESS, 10–21 PLANNED.

2026-10-01, continuare autorizată. Criterii înainte de cod: o singură intenție pentru factura inițială a unei comenzi, snapshot fiscal înghețat și criptat, revizia ciornei/profilului/conexiunii fixată, retry fără intenție nouă. Pregătirea locală și citirea prin API verifică sesiunea, CSRF/rol/granturi/tenant/store; nu acceptă snapshot/sume/chei de la browser. Coordonatorul intern reutilizează ExternalOperations (D11), reconstruiește contractul din snapshot, revalidează sursa/profilul/conexiunea înaintea apelului și păstrează cheia providerului la retry. UNKNOWN/crash nu permit replay automat; referința confirmată nu se emite iar. Nu activăm ruta de execuție, jobul sau capability/providerul real în acest lot. Registrul este separat de propunerea CMS și supraviețuiește ștergerii ei; retenția fiscală/privacy definitivă rămâne în 09/21, fără a inventa o obligație legală.

Verificare proporțională: retry și rollback, izolarea/criptarea/AAD, sursa/ciorna/profilul/conexiunea invalidată, retry temporar și rezultat necunoscut/reconciliat, concurență pe intenție și API/rol/CSRF. Migrare în app/test și inventar chei, o regresie finală. Fără schimbări UI sau probe externe. 09 unic IN_PROGRESS; celelalte module păstrează stările.

## Lot activ 09.2c — reconciliere și mapare locală

Publicare 09.2c.1: `a762d50` sincronizat pe codex/modul-01-arhitectura; push/fetch, HEAD/origin identice și checkout curat PASS. CI nou neconfirmat. Predarea ulterioară este numai documentară.

Rezultat 09.2c.1: criteriile lotului local îndeplinite. Core fiscal neutru/compatibil, verificări exacte și GET/UI reconciliation, mapper offline pentru TVA standard cu nomenclator unic. Opțiuni stoc/email/SPV/catalog 0, fără capability ori transport de scriere. PASS: 6 teste noi + HTTP profil invalidat extins, o regresie finală 257 teste/249 lint/PHPStan 8, JS/HTTP și browser desktop/mobil. [Raport](../testing/09-invoicing.md), D27. Transport fiscal incomplet, componente taxe multiple, tratamente speciale, valută și nomenclator ambiguu sunt blocaje explicite, nu mapări simulate. Fără migrare/date fiscale completate în fixture/provider extern.

Reluare exactă: 09.3a — operația durabilă/snapshot/cheie stabilă și revalidare tenant/store/profil; apoi transport și răspuns, rezultat necunoscut, original/storno/PDF. Extinderile fiscale enumerate rămân backlog în 09 înaintea DONE; probe reale la final D26. Text de reluare: „Continuă în 09.3a pornind de la InvoiceAssembly și OblioInvoiceMapper offline; creează fluxul durabil local, fără a activa apeluri fiscale în contul real. Păstrează limitele fiscale restante și probele externe pentru final.” 09 IN_PROGRESS, 10–21 PLANNED.

2026-10-01, continuare cerută de utilizator. Criterii înainte de cod: contract fiscal Core neutru cu destinatar de facturare (fără adresă de livrare inventată), date document și TVA explicit, linii care păstrează baza cu/fără taxe; reconciliere exactă cantitate/preț/reducere/taxă/total, monedă și overflow, fără float sau ajustări ascunse. Ciornele/contractele existente rămân compatibile. Raportul și UI arată diferențele, iar sursa schimbată blochează pregătirea. Mapper Oblio offline cu reduceri alocate pe linie, nomenclator TVA explicit/reverificat în viitoarea emitere și opțiuni stoc/email/SPV/salvare catalog dezactivate; lipsa nomenclatorului ori tratamentul nesuportat blochează maparea, fără a inventa nume de cote. Transportul de scriere și capability de emitere rămân neactivate până la operațiile durabile 09.3; fără apel în cont. Probe proporționale: calcule/overflow, mapper, integrare GET/sursă/rol și compatibilitate, o regresie finală; verificare UI numai pentru afișarea nouă. D26 păstrează probele reale la final.

## Lot 09.2a — completarea ciornei, probe externe amânate

Publicare: `58b200b` sincronizat pe codex/modul-01-arhitectura, push/fetch și egalitatea HEAD/origin PASS, checkout curat. CI observat IN_PROGRESS, rezultat final neverificat. Predarea ulterioară nu modifică runtime.

Rezultat 09.2a.2.2b (2026-10-01): implementat și verificat local. PUT /api/invoice-order-drafts/{id} salvează doar completări normalizate, criptate în snapshot cu revizie și audit atomic; rol/grant/store/CSRF și sourceChanged verificate, retry identic fără revizie nouă. Refresh păstrează completările aplicabile și excepțiile produselor încă prezente. Formular cu date calendaristice, lipsuri client, emitent și unități/TVA comune plus excepții. Faptele CMS nu pot fi suprascrise; cota rămâne text, fără deducere din taxă. READY_FOR_MAPPING/canIssue=false, fără migrare sau apel extern. PASS: 5 teste noi, două procese/rollback extinse, o regresie finală 251 teste/240 lint/PHPStan 8, JS/HTTP și browser desktop/mobil. [Probe](../testing/09-invoicing.md).

Reluare exactă: 09.2c — extinderea contractului fiscal neutru și reconcilierea sumelor înainte de mapare; apoi 09.3, operații durabile locale. Nu relua completarea manuală a comenzii sintetice și nu recrea conturile/conexiunile. Probele reale se execută la final conform D26 și [registrului](../testing/final-integrations.md); 09 încă IN_PROGRESS pentru cod restant, 10–21 PLANNED. Text de reluare: „Continuă Ordely în 09.2c de la ciorna CMS cu completări persistate; dezvoltare locală, providerii reali se verifică la final conform D26.”

2026-10-01: utilizatorul cere reluarea secvențială a modulelor și păstrarea integrărilor/API KEY clare, cu verificare reală la final (D26). Lotul curent completează pregătirea locală a facturării, fără apel provider. Criterii înainte de cod: client/adresă preluate din CMS și completări explicite unde lipsesc; date document, adresă/statut emitent, tip/date fiscale client, unitate și tratament TVA cu valori zecimale text; completări persistate în snapshot-ul criptat existent, CAS/retry/audit și context merchant/store/rol/grant; banii și firma/seria nu pot fi înlocuite de payload-ul de completări. Actualizarea CMS păstrează completările și cere revalidare; sursă schimbată blochează salvarea completărilor până la refresh. UI poate salva incomplet, oferă valori comune explicite pentru linii și nu emite nimic. Stare distinctă pentru pregătirea mapării versus emitere; canIssue rămâne false. Verificări proporționale: validare domain, HTTP/CAS/criptare/roluri, o regresie finală și browser. Nu închidem 09 cu maparea/execuția durabilă încă restante.

Publicare schelet D25: cod `27f544a` sincronizat pe `codex/modul-01-arhitectura`; push/fetch PASS, HEAD/origin identice și checkout curat verificate. Verificările locale ale lotului au trecut; CI este declanșat automat la push și nu este declarat PASS fără rezultat. Nu s-au adăugat teste noi pentru ecranele de structură. Această predare modifică numai documentația.


## Direcție nouă — schelet transversal al aplicației (09-S)

2026-09-30: utilizatorul cere explicit scheletul întregii aplicații, nu rafinarea unui exemplu de facturare. Extindere autorizată de structură/prezentare în modulul activ: meniul și ecranele tuturor fluxurilor, componente comune, puncte de integrare și folderele contextelor viitoare. Nu închidem 09 și nu implementăm în paralel logica modulelor 10–21; stările lor rămân PLANNED. Verificările sunt proporționale: nu adăugăm teste pentru fiecare ecran gol; păstrăm verificările existente de securitate/bani și rulăm o singură regresie la finalul lotului.

Criterii înainte de cod: navigare funcțională către expediții/tracking/retururi/refuzuri/schimburi/retrimiteri/rambursări/portal/setări, cu rutare/reload/back și rolurile existente; pagini cu structură specifică fluxului, filtre și câmpuri prevăzute, fără înregistrări fabricate sau butoane care pretind operații reale; legături între fluxuri și funcțiile existente; un registru comun și componente reutilizabile; foldere PHP pe context/strat pregătite, contractele Core existente reutilizate. Curățare la merchant/store/logout; pagini viitoare fără apeluri externe, salvări, IBAN/date personale ori stoc management. Verificări: sintaxele JS, HTTP/regresie existente o dată și browser desktop/mobil pe navigarea nouă.

Rezultat: scheletul transversal livrat. 9 rute/ecrane, hartă pe Acasă, componente comune de listă/filtre/etape/câmpuri/legături și 5 contexte PHP pe patru straturi. Store/context din sesiunea existentă, roluri de vizibilitate și allStores pentru configurare; acțiunile încă neconectate rămân dezactivate prin fieldset. Fără date fabricate, logică/provider ori endpoint-uri simulate. Browser owner desktop/mobil, toate rutele/reload/Back/legături și consola PASS; sintaxe JS, 6 HTTP și o singură regresie existentă 246 teste PASS, fără teste noi. Corecția hărții Acasă a fost verificată separat după proba inițială. Testele obligatorii ale modulelor viitoare nu sunt declarate trecute. Reluare: conectarea unor fluxuri complete din această structură, cu restanțele fiscale păstrate, fără fragmentare la fiecare element de UI. 09 IN_PROGRESS, 10–21 PLANNED pentru funcții. [D25](../decisions.md), [raport](../testing/09-invoicing.md).

Publicare 09.2a.2.2a: cod `4350a86` trimis pe `codex/modul-01-arhitectura`; push, fetch și HEAD/origin identice verificate, checkout curat. [CI Windows/Linux 36707834906 PASS](https://github.com/razvanstav/ordely/actions/runs/36707834906), `gh run watch 36707834906 --exit-status --interval 15` exit 0: PHP/MySQL pe ambele sisteme, migrare/restart Windows și HTTP Linux. Regresie locală 246 teste, 238 lint, PHPStan 8, două sintaxe JS și 6 HTTP PASS. Linkuri/diff/staged scanner pentru secrete/date fiscale PASS. Captura nativă finală `var/ui-order-draft-20260930.jpg` este locală și ignorată. Această predare ulterioară modifică numai documentația; emitere/storno/PDF rămân NOT_RUN.


## Pas 09.2a.2.2a — ciornă din comandă CMS (implementat)

2026-09-30: utilizatorul confirmă că datele lipsă vor veni din Shopify și cere continuarea, fără a completa manual comanda de test acum. Descompunem persistarea: acest subpas salvează propunerea CMS incompletă ca ciornă locală distinctă de ciornele manuale 09.1; completările fiscale finale și contractul de emitere rămân în pasul următor. Nu presupunem că datele emitentului vin din Shopify; firma/seria rămân din configurarea Oblio.

Criterii înainte de cod: salvare din datele recitite pe server, fără payload de client care poate înlocui sumele/adresa; ID/versiune de comandă și profil obligatorii la salvare, CAS și retry identic fără dubluri/audit duplicat; criptare legată de merchant/store/comandă/versiuni și inventar de chei; listare/deschidere separată de ciornele manuale, snapshot păstrat până la actualizare explicită, avertizare la schimbarea comenzii/profilului/conexiunii; roluri owner/admin/finance pentru salvare, operator citire, viewer refuzat; CSRF/origin/merchant/store/granturi și rollback atomic cu audit. Datele sunt propuneri ne-fiscale: canIssue=false, fără număr, emitere, stoc sau gestiune refuzuri. Snapshot-urile propunerilor se elimină prin FK la ștergerea sursei pentru privacy; auditul păstrează numai ID-uri/versiuni.

Verificări planificate: unitare criptare/context/tamper; MySQL/HTTP CAS/retry/sursă schimbată/roluri/granturi/rollback/privacy; două procese reale; inventar chei; regresie Composer/JS/HTTP și Chrome desktop/mobil. Rezultatele vor fi notate după execuție. 09 rămâne singurul activ.

Rezultat: criteriile subpasului îndeplinite. Migrația 009 în app/test, snapshot criptat cu scop distinct și versiuni, serverul recitește sursa înainte de salvare; un retry identic păstrează revizia și auditul de salvare unic. API și listă distincte de 09.1, deschidere a snapshot-ului păstrat și actualizare explicită, sursă/profil/conexiune schimbate semnalate. Roluri/granturi și CSRF/origin validate, audit atomic fără date fiscale. FK șterge numai propunerea inițială la ștergerea sursei CMS pentru privacy; viitoarele documente fiscale au o politică separată (D24).

PASS: 6 teste noi (1 unit + 5 integration), inclusiv două procese reale, rollback și inventar chei; regresie 246 teste, 238 lint, PHPStan 8, JS/HTTP. Chrome desktop/mobil 390×844: 30 linii/36.00 RON, salvare, reload/redeschidere/Enter, resalvare revizia 1 fără dubluri, filtrare/focus/consolă și fără overflow. [Probe](../testing/09-invoicing.md). Propunerea locală este întotdeauna INCOMPLETE/canIssue=false. Emitere/storno/PDF NOT_RUN.

Reluare exactă: 09.2a.2.2b — completările fiscale persistate și snapshot fiscal neutru (emitent, destinatar, adrese, unități/tratamente TVA/date document), apoi 09.2c/09.3. Facturare arată ciorna comenzii de test salvată local; nu recrea conexiunile și nu cere completarea manuală a fixture-ului acum. Modulul 09 și 09.2a.2 nu sunt încă închise; 10–21 PLANNED. Publicarea se consemnează după verificare.

Publicare 09.2a.2.1: cod `6556a4f` trimis pe branch-ul proiectului, HEAD/origin identice și checkout curat verificate. [CI Windows/Linux 36701852570 PASS](https://github.com/razvanstav/ordely/actions/runs/36701852570), urmărit cu `gh run watch 36701852570 --exit-status --interval 10` până la exit 0. Regresie locală: 240 teste, 232 lint, PHPStan 8, două sintaxe JS și 6 probe HTTP PASS. Linkuri relative, diff/staged check și scanner staged pentru secrete/date fiscale PASS. Această predare ulterioară modifică numai documentația; nu pretinde o nouă probă de emitere.

## Pas 09.2a.2.1 — pregătirea datelor din CMS (implementat)

Autorizat 2026-09-30: utilizatorul confirmă continuarea dezvoltării și preluarea datelor din CMS. Primul subpas al 09.2a.2 este o vedere numai de citire, pornită dintr-o comandă importată. Criterii înainte de cod: client/adresă exclusiv din billingAddress, fără fallback tacit la livrare; monedă, cantități, prețuri, reduceri și taxe păstrate exact, cu baza cu/fără taxe explicită; fără deducerea cotei TVA sau CUI din texte; profilul firmei/seriei verificat pentru același merchant/store; listă de lipsuri și blocaje, fără a declara factura gata de emitere. Context/rol/granturi la HTTP și repository, no-store și audit fără conținut personal. Vedere în Facturare, cu date șterse la schimbarea magazinului/comerciantului/logout și protecție împotriva răspunsurilor întârziate. Ciornele existente rămân neschimbate; nu emite, nu scrie în CMS/stoc și nu implementează refuzuri/AWB.

Verificări planificate: unitare pentru adrese lipsă, firmă fără CUI, taxă fără cotă, sume exacte/monede incompatibile și comenzi modificate; integrare MySQL/HTTP pentru izolare, granturi, roluri, profil invalidat și audit sigur; Composer check, sintaxe JS, smoke HTTP și browser desktop/mobil.

Rezultat: criteriile subpasului îndeplinite. GET /api/invoice-preparation/{orderId}?storeId=... citește numai proiecția CMS criptată și profilul local; nu instanțiază un provider extern. Tranzacție cu verificări de acces și citiri FOR SHARE, audit numai ID-uri/versiuni; no-store. Raport neutru pentru client/adresă, profil și sume exacte, baza prețurilor, linii cu identități stabile și probleme cu cod/path/mesaj. Modificările/rambursările, lipsurile, moneda/precizia incompatibile, transportul fără detalii fiscale și conexiunea schimbată sunt explicite; canIssue=false. UI pornește din comenzi, grupează problemele repetitive și păstrează liniile în detalii extensibile; protecție epoch/sequence, eliminarea datelor la închidere/context/logout. 9 teste noi (79 assertions) PASS, PHPStan 8 și Chrome desktop/mobil PASS; [regresie și comenzi finale](../testing/09-invoicing.md).

Reluare 09.2a.2.2: completări persistate criptat și snapshot fiscal (inclusiv emitent, destinatar, adresă, unitate/tratament TVA/date document) cu legătura/versionarea comenzii și profilului; apoi adaptarea contractului de emitere. Nu se deduc cote din sume, nu se completează valori lipsă inventate. Ciornele 09.1 sunt păstrate; proba reală de emitere este NOT_RUN. 09.2a.2 nu este închis prin această vedere.

## Reguli acceptate — 2026-09-30

D01 — reguli de produs acceptate: diferența de preț la schimb se introduce manual, fără compensare automată. Bifa Transport preia tariful configurat în site și facturează pe datele inițiale ale clientului; AWB se generează automat cu COD egal cu diferența manuală plus transportul selectat. Nimic/total zero la schimb sau retrimitere înseamnă fără factură nouă/COD 0; regula nu elimină factura unei comenzi obișnuite deja plătite.

Storno automat numai la înregistrarea unui refuz de primire, pe original verificat; returul obișnuit nu declanșează storno. Facturile externe se acceptă numai după identificare/verificare în Oblio; original neverificabil blochează storno. Refuzul repetat nu dublează storno; rezultat necunoscut cere reconciliere conform D11. Planul nu avea un modul distinct pentru refuzuri: fluxul este păstrat în backlog-ul 16, corelat cu tracking/recepție, fără implementarea lui acum.

D04 — gestiunea stocului, reintegrarea, ajustările și rezervările sunt amânate explicit pentru o zonă separată. Oblio va emite fără modificări de stoc; citirea disponibilității rămâne separată. Email și SPV fără trimitere automată în etapa de test, acceptat. Mediul de emitere D08, validarea fiscală și probele reale de emitere/storno rămân restante. Aceste decizii actualizează propunerile istorice de mai jos; nu certifică providerul.

Reluare: 09.2a.2 — date complete și lista lipsurilor; 09 rămâne singurul modul activ. Schimbare numai documentară; fără emitere sau teste runtime rerulate.


2026-09-30. **IN_PROGRESS**, unicul modul activ. 09.1, 09.2b și UI D21 sunt păstrate. 09.2a.1 finalizat: firmă/serie salvate criptat pe magazin, proba reală și rezumatul în Facturare PASS. Urmează 09.2a.2, datele complete și verificarea pregătirii; nu există încă emitere.

## Pas 09.2a.1 — salvarea firmei și seriei

Utilizatorul cere continuarea dezvoltării și confirmă propunerea de configurare a facturării. Primul pas verificabil din 09.2a salvează firma și seria pentru fiecare magazin, separat de credentiale. Criterii: selecție explicită din nomenclatoare reverificate pe server la salvare; un profil per merchant/store, conținut criptat și versiune optimistă; retry identic fără revizie/audit duplicat; refuz pentru firmă/serie inexistente, conexiune schimbată/dezasociată și acces revocat inclusiv în timpul rețelei; audit cu ID-uri/versiune, fără date fiscale; inventarul cheilor include profilurile. Administrare owner/admin cu acces la toate magazinele, citire după permisiunea existentă invoices.read/granturi. UI Oblio cu pas distinct „Salvează configurarea”, rezumat în Facturare și mesaj de reverificare la schimbarea conexiunii; desktop/mobil și tastatură. Ciornele existente rămân compatibile. Nu se deduce TVA și nu se emite nimic. Adresele complete, destinatarul, liniile/TVA și verificarea întregii facturi sunt următoarele subetape ale 09.2a; D01/D08 pentru emitere rămân deschise.

Verificări planificate: teste MySQL/HTTP pentru criptare, izolarea tenant/store, CAS/retry, rollback și modificarea accesului în timpul apelului; regresie Composer, JS/HTTP și browser. Rezultatele se completează după execuție.

Rezultat PASS: migrația 008, API GET/POST, selecția verificată live la salvare, revalidare după rețea și persistare atomică criptată/auditată. Un profil per merchant/store, CAS/retry identic; schimbarea conexiunii afișează nevoia de reverificare. UI cu pas de salvare separat înaintea listelor de nomenclatoare, rezumat în Facturare și link direct la Oblio. Teste finale: 231 (112 unit/768 assertions, 119 integration/750), 226 lint, PHPStan 8, JS și 6 HTTP. Include două procese concurente, rollback provocat al tranzacției și inventarul CLI al cheilor. Proba reală Chrome: singura firmă/serie disponibile salvate local și păstrate după reload; desktop/mobil 390×844 fără overflow, focus/Back PASS, fără erori de aplicație. Captura ignorată; datele fiscale și secretele nu intră în Git. Nu s-au schimbat credentiale și nu s-au emis documente. [Comenzi și limite](../testing/09-invoicing.md). Primul subpas închis; restul 09.2a rămâne de implementat.

## Pas UI — categorii și ecran dedicat Oblio, 2026-09-30

Publicare 09.2a.1: cod `45ac9ba`, corecție de test `1fcf8e4`, push și egalitatea HEAD/origin verificate. [CI Windows/Linux 36687850355 PASS](https://github.com/razvanstav/ordely/actions/runs/36687850355). Prima rulare Linux a eșuat la crearea unui trigger privilegiat în test; proba de rollback a fost adaptată fără schimbarea permisiunilor. Scanarea staged include secretele și datele fiscale ale profilului, fără potriviri; linkuri/diff check PASS. Predarea ulterioară este numai documentară.

Cerere explicită: integrarea de facturare trebuie separată de curieri, iar apăsarea pe Oblio trebuie să deschidă opțiuni lizibile. Extindere de prezentare în 09 (D21), fără implementarea serviciilor viitoare. Criterii înainte de cod: catalog grupat Facturare/Curierat/Magazine; serviciile indisponibile au stare explicită și nu pot fi conectate; apăsarea pe Oblio deschide numai detaliile sale, iar formularul pentru un cont nou apare la cerere; text și câmpuri mai mari, spațiere și secțiuni pentru firmă/serie/TVA, magazin și acces; TVA/seriile afișate în liste structurate; setările tehnice închise implicit; navigare înapoi, reload, focus și mobil fără overflow. Contextul/rolurile și API-urile existente sunt păstrate; datele reale și secretele nu intră în Git. Verificări planificate: browser desktop/mobil și traseele Oblio/Shopify, JS syntax, smoke HTTP și regresia existentă. Rezultatele se consemnează după execuție.

Rezultat PASS: toate criteriile de prezentare de mai sus implementate. Catalog cu stări reale, curieri marcați „În pregătire”, `#integrations/oblio` și `#integrations/shopify` cu conexiuni/istoric separate. Formularul unui cont suplimentar este la cerere și se închide după salvare; selectorul tehnic de simulatoare nu apare în fluxul Oblio. Seriile/TVA se citesc în doi pași, cu etichete implicite distincte de alegerea fiscală. Desktop/mobil 390×844, Back/reload/Enter/focus, formular de acces, ștergerea câmpurilor sintetice și citirile reale PASS. 220 teste, 222 lint, PHPStan 8, două sintaxe JS și 6 HTTP PASS; [probe și limite](../testing/09-invoicing.md). Nicio mutație de cont sau document în această probă UI. Preview PHP/MySQL repornit local; extensia Chrome funcționează. Pasul UI este închis, 09 rămâne IN_PROGRESS.

Cod UI `a357c81` publicat; hash local/origin verificat identic și [CI Windows/Linux 36681823310 PASS](https://github.com/razvanstav/ordely/actions/runs/36681823310). Capturile sunt ignorate, staged diff fără secrete și linkuri relative/diff check PASS. Predarea ulterioară actualizează numai documentația.

## Pas 09.1 — ciorne locale

- [x] Ciornă independentă de CMS/provider: referință, destinatar, adresă/date opționale și 1–50 linii, RON/EUR explicit.
- [x] Bani numai integer minor units; preț net unitar × cantitate − reducere netă + taxă introdusă explicit; total calculat pe server, fără rată fiscală presupusă.
- [x] Schema versionată, conținut criptat și revizii; separare merchant/store și permisiuni owner/admin/finance pentru editare, operator doar citire, viewer fără acces.
- [x] Creare repetată fără duplicare; editare/arhivare cu versiune și concurență reală; audit/outbox atomic fără date personale.
- [x] UI/API creare, calcul, listare, detalii, editare, arhivare; nicio emitere externă și niciun număr fiscal atribuit.
- [x] Teste unitare, MySQL, roluri/granturi/CSRF, HTTP real, criptare, rollback și două procese concurente; CI și Git verificate pentru predarea pasului.

09.1 finalizat: 207 teste locale PASS, 213 lint, PHPStan 8, JS și HTTP. Codul f622aab este publicat cu hash remote verificat și [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). [Operare](../invoicing.md), [raport](../testing/09-invoicing.md). Inspecția vizuală manuală este NOT_RUN; nu se confundă cu probele HTTP.

## Pas 09.2b — conexiune locală și citire (finalizat)

### Proba reală a contului, 2026-09-30

După furnizarea datelor API și confirmarea emailului de către utilizator, conexiunea a fost salvată prin UI și asociată ca implicită magazinului existent. Autentificarea și citirile reale au trecut: 1 firmă, 1 serie de factură, 10 cote TVA. Helper-ul local de verificare exclusiv prin citire confirmă o singură conexiune activă, versiunea 2, credentiale criptate, asocierea corectă și două citiri auditate numai cu versiune/număr de rezultate. Nu sunt salvate date reale în predare/Git. [Comenzi și probe](../testing/09-invoicing.md).

09.2b este finalizat; rezultatul real completează testele simulate și CI din implementare. Nu s-a schimbat cod runtime și nu s-a rerulat suita completă. Alegerea firmei pentru această citire nu salvează profilul de emitere, nu stabilește sandbox-ul și nu acceptă implicit o cotă TVA ori politica D01. Niciun document nu a fost emis. Ultima comandă cosmetică de închidere a formularului a fost blocată de necesitatea actualizării extensiei Chrome, după confirmarea rezultatelor și fără a afecta proba reușită.

### Acces local cerut de utilizator, 2026-09-30

Utilizatorul cere acces rapid și confirmă explicit că datele oferite sunt pentru contul local Ordely. Dependență de operare în 09: pregătim contul personal în spațiul de dezvoltare existent, păstrând magazinul și conexiunile. Criterii înainte de execuție: numai DB locală de dezvoltare, parola persistată exclusiv ca hash, context merchant verificat, login prin fluxul HTTP normal și formularul Oblio accesibil; emailul/parola nu intră în Git sau predare. Parola aleasă este sub limita provisionării standard, astfel setarea administrativă punctuală este limitată la contul local cerut, fără schimbarea validatorului general. Publicarea online cere un mediu PHP/MySQL separat și o parolă conformă politicii; Git privat nu constituie hosting privat.

Rezultat PASS: un cont owner cu acces la magazinul existent, hash verificat fără afișare, login real și deschiderea formularului Oblio în Chrome. Contul sintetic anterior și datele sale rămân păstrate. În acest prim pas de acces nu s-a salvat conexiunea Oblio și nu s-a apelat providerul; conectarea ulterioară este consemnată mai sus. Fără cod runtime nou; scriptul administrativ temporar fără valori de credentiale a fost eliminat după folosire. Alegerea hosting-ului și deployment-ul rămân separate. D20, [probe](../testing/09-invoicing.md).

Utilizatorul a ales explicit continuarea locală, fără hosting acum.

### Pas UI cerut de utilizator, 2026-09-29

Înainte de continuarea conectării, utilizatorul cere gruparea și simplificarea ecranului actual. Extindere explicită de scop în 09: reorganizăm suprafețele deja implementate, fără funcțiile viitoare ale modulului 20. Criterii înainte de cod: navigare clară cu o singură zonă principală vizibilă, vedere de ansamblu cu pași/acțiuni reale, separare activități zilnice/configurare/diagnostic, formulare și opțiuni avansate dezvăluite la cerere, vocabular accesibil, desktop/mobil fără overflow, focus/tastatură și roluri păstrate. Verificăm în browser navigarea, facturarea și traseul de conectare fără apeluri externe sau salvarea unor credențiale reale; rulăm verificările de sintaxă/HTTP și regresie relevante. Modulul 09 rămâne unicul IN_PROGRESS.

- [x] Șase pagini cu meniu persistent, URL/back și focus: Acasă, Comenzi și catalog, Facturare, Magazine, Integrări, Activitate sistem.
- [x] Acasă cu numere din API-ul local și ghid bazat pe magazine/conexiuni asociate; conexiunea salvată nu este prezentată ca probă de acces real.
- [x] Formulare Shopify/Oblio la cerere; setări avansate și conexiuni dezactivate în detalii închise; activitate filtrată implicit la ce necesită atenție.
- [x] Design adaptiv și etichete în română; o singură pagină vizibilă și fără overflow pe toate cele șase pagini la 390×844; tastatură/focus verificate în Chrome. Condițiile de rol sunt păstrate în cod; probele browser folosesc owner, nu pretind testarea vizuală a tuturor rolurilor.
- [x] Verificări: 220 teste, 222 lint, PHPStan 8, JS și 6 HTTP PASS; calcul de ciornă sintetică 12.00 RON fără salvare și fără apel extern. [Dovezi](../testing/09-invoicing.md), D19.

Pagina finală a probei UI din 2026-09-29: Acasă. Formularul Oblio se deschide din ghid sau Integrări → Oblio. Pasul UI este finalizat; proba reală Oblio restantă atunci a fost închisă la 2026-09-30.

Cod UI `b1709f0` publicat; hash local/remote identic, scanare staged fără secrete, linkuri relative și diff check PASS. [CI Windows/Linux 36575021341 PASS](https://github.com/razvanstav/ordely/actions/runs/36575021341). Actualizarea ulterioară este numai documentară.

### Implementarea conexiunii 09.2b

Utilizatorul confirmă un cont Oblio și autorizează pregătirea integrării locale. Începem cu 09.2b, independent de regulile D01 încă neconfirmate. Nu presupunem că firma deschisă în browser este sandbox. Acest pas nu include emitere, storno, email, stoc sau SPV și nu salvează încă profilul de emitere.

Criterii de acceptare înainte de cod: credentiale criptate prin infrastructura existentă; formulare dedicate email/cheie API; citire autenticată firme/serii de factură/cote TVA; contract neutru în Core; control merchant/store/rol/conexiune înainte și după apel; erori sigure auth/429/timeout/JSON invalid; nicio capabilitate de emitere; teste unitare/MySQL/HTTP și verificare UI. Proba reală de citire se raportează separat de transportul simulat. Datele reale și secretele nu intră în documentație/Git.

Implementat: port InvoiceConfigurationReader și rezultat neutru, adaptor Oblio cu transport HTTPS limitat, registry și validarea credentialelor înainte de persistare, API /api/invoice-configuration, audit fără datele firmei, formulare adaptate providerului și selecție de firmă numai pentru citire. Tokenul temporar rămâne în memoria cererii; TVA este text zecimal exact. Nicio migrație nouă și nicio salvare automată de profil fiscal.

Probe locale la implementare PASS: 220 teste (112 unit/768 assertions, 108 integration/664 assertions), 222 lint, PHPStan 8, JS și 6 HTTP. UI login/formular inspectate în Chrome. Încercarea inițială cu valori mascate a fost oprită de validarea browserului, fără salvare; proba reală a rămas atunci NOT_RUN. După furnizarea datelor de către utilizator, proba din 2026-09-30 de mai sus este PASS și închide 09.2b.

Cod `11dddf4` publicat, hash remote identic; [CI Windows/Linux 36551856411 PASS](https://github.com/razvanstav/ordely/actions/runs/36551856411).

## Pașii următori ai modulului, încă neimplementați

Pregătirea este documentată în [planul integrării Oblio](../oblio-integration.md). Regulile D01 sunt acceptate; firma/seria, vederea CMS, completările persistate, contractul fiscal/reconcilierea și mapperul local, plus intenția durabilă 09.3a sunt implementate. Rămân 09.3b, original/storno/PDF și extinderile fiscale D27; mediul D08 și probele provider sunt mutate la final prin D26 și nu sunt declarate verificate.

09.2a.1 a salvat firma/seria. 09.2a.2: validarea datelor complete, snapshot emitent/destinatar, adrese și linii/TVA explicit; D01 înaintea codului de politică. 09.2c: maparea documentelor în adaptor. Conexiunea și citirea contului din 09.2b sunt verificate. 09.3: emitere/storno/reconciliere/PDF prin operații durabile și proba controlată. Mediul și opțiunile D08 se stabilesc înainte de probe fiscale externe; selecția locală nu dovedește izolarea fiscală.

09 nu este DONE după 09.1. Ciorna locală nu este document fiscal, nu declanșează rambursare/storno și nu dovedește integrarea Oblio. Nu începem 10.

## Punct de reluare pe alt PC / în altă conversație

> Verifică Git și STATUS. 01–08 DONE; numai 09 IN_PROGRESS. Continuă 09.3b de la IssueIntents și D28: anulare/replanificare sigură pentru intenția netrimisă, apoi răspuns/document fiscal și transport local, fără apel în contul real sau activare prematură UI. Snapshot folosit/UNKNOWN nu se suprascrie. Citește docs/oblio-integration.md și D01/D04/D08/D26/D27/D28. Păstrează profilul/conexiunile și fixture-ul incomplet existent; nu cere chei din nou. Extinderile fiscale, original/storno/PDF rămân în 09; probe reale la final D26. Stoc separat; nu începe 10–21. Pe alt PC DB/keyring nu vin prin Git. Rezultatele și punctul exact sunt în docs/testing/09-invoicing.md. Actualizează predarea și Git.
