# Modul 09 — Facturare

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

Pregătirea 09.2 este documentată în [planul integrării Oblio](../oblio-integration.md). Regulile de produs D01 sunt acum acceptate la 2026-09-30, firma/seria sunt salvate, iar vederea din CMS 09.2a.2.1 este implementată. Completările persistate/snapshot-ul fiscal 09.2a.2.2, maparea 09.2c și emiterea 09.3 rămân; mediul de emitere D08 și validarea fiscală/provider nu sunt închise.

09.2a.1 a salvat firma/seria. 09.2a.2: validarea datelor complete, snapshot emitent/destinatar, adrese și linii/TVA explicit; D01 înaintea codului de politică. 09.2c: maparea documentelor în adaptor. Conexiunea și citirea contului din 09.2b sunt verificate. 09.3: emitere/storno/reconciliere/PDF prin operații durabile și proba controlată. Mediul și opțiunile D08 se stabilesc înainte de probe fiscale externe; selecția locală nu dovedește izolarea fiscală.

09 nu este DONE după 09.1. Ciorna locală nu este document fiscal, nu declanșează rambursare/storno și nu dovedește integrarea Oblio. Nu începem 10.

## Punct de reluare pe alt PC / în altă conversație

> Verifică Git și STATUS. 01–08 DONE; numai 09 IN_PROGRESS. 09.2a.2.1 disponibil din Comenzi → Pregătește facturarea: vedere locală client/adresă de facturare, profil, sume/linii exacte și lipsuri; canIssue=false. Urmează 09.2a.2.2: completări persistate criptat și snapshot fiscal legat de versiunea comenzii/profilului, cu compatibilitate 09.1. Citește docs/oblio-integration.md și D01/D04/D08/D22/D23. Diferență manuală + transport, zero fără factură/COD, storno numai automat la refuz pe original verificat; gestiunea stocului amânată, email/SPV fără trimitere la teste. Profilul și conexiunile existente se păstrează; pe alt PC DB/keyring nu vin prin Git. Nu începe 10–21 și nu emite fără mediul D08 confirmat. Rezultatele/verificările sunt în docs/testing/09-invoicing.md. Actualizează predarea și Git.
