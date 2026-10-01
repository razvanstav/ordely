# Registru de decizii

## D29 — Anulare netrimisă și document verificat atomic (09.3b.1)

Ales 2026-10-01: intenția are lifecycle ACTIVE/CANCELLED și versiune proprie. Unicitatea privește numai intenția activă per comandă; cele anulate rămân cu snapshot-ul nemodificat. Anularea explicită/CAS și retry sunt permise doar dacă nu există nicio rezervare Operations, indiferent de status. Se poate apoi pregăti o intenție nouă din revizia curentă, cu alt ID/cheie. Nu se anulează o factură externă. Hook-ul reservationCheck rulează în tranzacția Operations și ia aceleași lock-uri ca anularea (store → intent → operation), închizând fereastra dintre verificarea inițială și rezervare. Aceasta este o dependență necesară 09, nu un nou modul activ.

Portul de execuție cere acum InvoiceSnapshot, nu doar ExternalId. Totalul/moneda, tipul inițial, starea neanulată și numărul sunt validate față de snapshot-ul fiscal; rezultat incompatibil devine UNKNOWN. Hook-ul onConfirmed persistă documentul criptat și auditul în aceeași tranzacție cu confirmarea/fencing Operations. Unicitate pe intent/operație și conexiune/referință; eșecul persistării lasă IN_FLIGHT, apoi UNKNOWN la expirarea lease-ului, fără replay. Reconcilierea internă cu rezultat verificat/evidence/versionare salvează atomic confirmarea și documentul; retry identic nu dublează auditul/documentul. Numărul și totalul sunt criptate, reference_hash este digest, iar AAD leagă rezultatul de operație/intent/tenant/store și snapshot. Inventarul cheilor include rezultatele; accesul de citire rămâne invoices.read + grant.

API-ul adaugă numai anularea locală, metadatele intentVersion/canCancel/cancelledAt și documentul verificat. documentVerified separă o referință Operations confirmată generic de un rezultat fiscal complet: ruta generică de reconciliere 05 nu inventează un document. Nu se acceptă snapshot/rezultat fiscal de la browser și nu există endpoint/job de execuție. Retenția fiscală/privacy definitivă și folosirea originalului pentru storno rămân restante.

OblioInvoiceResponse este pur/offline: citește seria/numărul din receipt și cere o listare ulterioară în firma/seria/numărul cererii pentru ID, stare și total. Nu copiază totalul solicitat drept dovadă. Păstrează zerourile numărului; verifică monedă/precizie/date/tip/stoc și total exact, permite numai zerouri suplimentare de precizie, fără rotunjire sau float. Referința neutră folosește ID-ul documentului; linkurile și payload-ul brut nu se salvează. [Documentație oficială](https://www.oblio.eu/api), consultată public, fără apel în cont. Transportul fiscal și capabilitățile reale rămân neactivate; conectarea locală a transportului/UI este 09.3b.2, probele reale la final D26. 09 IN_PROGRESS.

## D28 — Intenție inițială de facturare și execuție durabilă locală

Ales 2026-10-01 în continuarea 09.3a: invoice_issue_intents păstrează o intenție imutabilă per merchant/store/comandă pentru factura inițială. Nu limitează facturile/storno legitime ale fluxurilor viitoare. Snapshot-ul complet este criptat în chunk-uri cu AAD legat de tenant/store/intent/order/revizia ciornei/conexiune și versiuni; hash-ul verifică integritatea semantică. Browserul trimite doar storeId/orderId/expectedVersion; nu alege cheia, snapshot-ul ori sumele. Retry identic întoarce intenția existentă, iar altă revizie produce conflict. Pregătirea folosește mutex-ul magazinului și audit atomic, fără job sau număr fiscal.

IssueIntents.execute este un port intern neexpus prin HTTP, cu callback tipat pentru viitorul adaptor. Reutilizează ExternalOperations fără modificarea algoritmului D11: business key stabilă invoice-initial:ID, provider key persistentă operation:ID după rezervarea externă, payload Operations numai referințe/digest/revizie. Snapshot-ul se revalidează înaintea rezervării și înaintea apelului; actorul/rolul/granturile, ciorna/sursa/profilul și conexiunea activă/versiunea ei sunt obligatorii. Apelul rulează în afara tranzacției. Retry cert temporar păstrează cheia; UNKNOWN ori lease expirat nu reapelează și cere reconcilierea D11. Confirmarea și rezultatul necunoscut sunt citite din registrul Operations, fără o a doua stare concurentă în Invoicing.

Permisiunea invoices.issue este definită în 09 pentru owner/admin/finance; operator citește, viewer este refuzat, iar granturile se revalidează. API-ul expune numai pregătirea/citirea metadatelor și executionEnabled=false. Nu există rută de execuție, worker fiscal activat, capabilitate nouă Oblio, apel real sau efect asupra stocului. Nomenclatorul proaspăt, transportul/răspunsul fiscal complet și persistarea documentului sunt 09.3b; callback-urile sintetice confirmă doar coordonarea locală.

Registrul nu are FK CASCADE către proiecția/ciorna CMS: ștergerea sursei nu șterge dovada unei operații externe și rezultatul rămâne consultabil, dar sursa lipsă blochează execuția. Retenția/privacy fiscală definitivă nu este stabilită de această migrare și rămâne în 09/21. Înaintea conectării UI/execuției reale trebuie completată anularea/replanificarea explicită a unei intenții încă netrimise, cu protecție față de rezervarea concurentă; nu suprascriem un snapshot folosit ori un rezultat necunoscut pentru a permite o nouă factură. 09 rămâne IN_PROGRESS; D26 păstrează probele de provider pentru final.

## D27 — Contract fiscal și reconciliere înaintea mapperului

2026-10-01, continuare autorizată în 09. Liniile fiscale păstrează prețul unitar și reducerile în baza importată, cu/fără taxe; nu fabricăm un preț net unitar dintr-un brut rotunjit. InvoiceTax acceptă procent text cu patru zecimale și calculează în unități minore, cu rotunjire half-up pe linie și înmulțire descompusă fără float. Preț × cantitate, reduceri alocate, taxe, totaluri de linie/document și monedă trebuie să coincidă exact; diferența de un ban este blocaj, fără linie de ajustare sau cotă dedusă. Nu certificăm politica fiscală a contului din acest calcul local.

Core are FiscalCustomer cu billing fără shipping inventat, FiscalLine și InvoiceDetails; InvoiceDraft acceptă separat contractul legacy sau cel fiscal complet, fără amestec. Ciornele 09.1 rămân compatibile. GET ciornă CMS adaugă reconciliation: INCOMPLETE/MISMATCH/RECONCILED, totaluri/erori și readyForProvider pentru contractul neutru; canIssue=false. Sursă/profil schimbat blochează pregătirea, chiar dacă sumele snapshot-ului vechi coincid.

OblioInvoiceMapper este pur, fără rețea; cere profil/nomenclator verificate de viitoarea operație durabilă, firmă/nume/serie identice și o singură cotă cu procentul ales explicit. Numele cotei nu se inventează și valoarea default nu decide tratamentul. Reducerea valorică se pune imediat după produs, fără aplicare globală; sumele/procentele JSON sunt text exact. useStock/sendEmail/spvExtern și salvarea clientului/catalogului sunt 0, fără date personale suplimentare sau încasare implicită. Semantica efectivă și setările contului rămân în proba D08; transportul de scriere și capability de emitere nu sunt activate. [Sursă oficială](https://www.oblio.eu/api), consultată doar documentar.

Limite explicite: RON/2 zecimale, produse cu cel mult o componentă fiscală și TVA standard identificat fără ambiguitate în nomenclator. Transportul nenul fără linie fiscală completă, valuta, scutirea/în afara sferei și nomenclatoarele ambigue nu se emit; rămân extinderi ale 09 înaintea închiderii lui. La 2026-10-01 documentația oficială prezintă și storno total prin referenceDocument de tip Factura; refund privește eliminarea încasării asociate și nu trebuie confundat cu rambursarea către client. Citirea originalului, răspunsul/efectul extern și această mapare se gestionează în continuarea 09, cu probe reale la final prin D26.

## D26 — Continuare pe module, probele reale ale integrărilor la final

2026-10-01: utilizatorul cere reluarea secvențială, treptată, a modulelor și puncte de integrare clare, inclusiv credentialele, dar verificarea cu furnizorii reali la final. Implementarea și verificările locale obligatorii rămân în modulul activ; probele cu cont/API extern sunt mutate explicit în [registrul final de integrare](testing/final-integrations.md). Această amânare autorizată înlocuiește cerința istorică de a executa fiecare probă reală înaintea trecerii la modulul următor. Restanțele funcționale țin modulul activ; după închiderea lor și a verificărilor locale, probele externe amânate nu blochează dezvoltarea secvențială. DONE pentru un modul nu certifică un provider sau lansarea, iar probele finale nu sunt marcate PASS din mock-uri.

Configurarea existentă se păstrează: Shopify are flux OAuth separat, Oblio are email/clientId și API KEY/clientSecret criptate. Curierii vor avea schema exactă în modulele lor; nu cerem chei și nu construim formulare care salvează credentiale fără adaptor. Nu apelăm providerii în lotul curent. Emiterea, email/SPV și orice efect fiscal extern cer ulterior mediul/opțiunile D08 stabilite; stocul rămâne amânat conform D04.

09.2a.2.2b păstrează completările în același snapshot criptat al propunerii CMS. Firma/seria și sumele sunt date server-side; clientul poate completa numai lipsurile și alegerile fiscale explicite. Date calendaristice, statut TVA, unități și cote zecimale text, fără valori deduse. Valorile comune au excepții legate de ID-ul stabil al liniei; o excepție TVA nu împrumută cota altui tratament. Refresh păstrează completările aplicabile, elimină doar excepțiile produselor dispărute, iar datele noi CMS au prioritate. READY_FOR_MAPPING indică date pregătite pentru mapare, nu concordanță contabilă verificată; canIssue=false. Operația locală are versiune/CAS, retry identic, acces și audit atomic fără conținut fiscal.

## D25 — Scheletul întregii aplicații înaintea aprofundării facturării

2026-09-30: utilizatorul respinge ritmul de subpași mici/exemple și clarifică explicit „Scheletul întregii aplicații”. Prioritatea este structura comună: navigare, ecrane, legături între fluxuri, componente și contexte PHP pregătite. Aceasta este o extindere transversală autorizată a lotului curent, fără a declara implementate funcțiile viitoare și fără a marca 09 DONE. Logica modulelor 10–21 rămâne PLANNED și se conectează secvențial după schelet; lipsurile comenzii sintetice nu blochează construirea structurii.

Registrul de ecrane este resources/workspace.js; backend-ul PHP existent servește acest asset, iar autorizarea rutelor UI păstrează rolurile/configurarea allStores. Ecranele neconectate arată starea reală, fără date fabricate, API-uri de scriere simulate sau pretinse documente/AWB/plăți. Acțiunile/câmpurile viitoare sunt dezactivate prin fieldset, inclusiv în timpul reactivării globale a butoanelor. Aceste restricții UI nu înlocuiesc autorizarea server-side a viitoarelor comenzi.

Verificări proporționale: nu se adaugă teste care repetă fiecare ecran gol; o singură regresie existentă la sfârșitul lotului, sintaxe și probe de navigare/layout. Testele de bani, acces și efecte externe existente se păstrează. Folder-ele viitoare folosesc Domain/Application/Infrastructure/Presentation, porturile Core deja definite, fără servicii false care întorc succes. Stoc management/reintegrare rămâne amânat conform D04.

## D24 — Ciorna inițială din CMS, distinctă de documentul fiscal

2026-09-30: utilizatorul confirmă că datele lipsă vin din Shopify și cere continuarea fără completarea manuală a comenzii de test. Persistăm mai întâi propunerea CMS incompletă, recitită pe server, separat de ciornele manuale 09.1. O propunere inițială revizuibilă per merchant/store/comandă; nu limitează viitoarele facturi/storno asociate comenzii. Completările fiscale și contractul de emitere rămân în 09.2a.2.2b.

Snapshot criptat cu AAD pentru merchant/store/comandă și versiunile sursei/profilului/reviziei; audit numai ID-uri/versiuni, CAS și retry identic fără revizii duplicate. Actualizarea din CMS se face explicit, fără suprascriere la sincronizare. Modificarea profilului/conexiunii este semnalată chiar dacă versiunea profilului nu crește. Compararea folosește JSON canonic, pentru a nu confunda ordinea cheilor după decriptare cu schimbarea datelor.

Propunerea ne-fiscală este dependentă de sursa CMS și se elimină prin FK CASCADE la ștergerea sursei pentru privacy. Acest comportament nu decide păstrarea viitoarelor documente fiscale emise. Cheile folosite de chunk-uri intră în inventarul CLI; nu sunt eliminate automat. Rolurile și granturile se verifică la fiecare operațiune; fără emitere, număr fiscal, email/SPV, stoc sau implementarea refuzurilor.


## D23 — Date CMS înainte de snapshot-ul fiscal (09.2a.2.1)

2026-09-30, continuare autorizată: utilizatorul confirmă preluarea datelor clientului/comenzii din CMS. Separăm proiecția importată de o ciornă fiscală: prima vedere este locală, numai de citire, cu proveniență/versionare și lipsuri, fără InvoiceProvider sau emitere. Prețurile originale/după reduceri, taxele și baza cu/fără taxe rămân fapte distincte; nu deducem unitatea, cota TVA, CUI sau tipul fiscal. Adresa de livrare nu înlocuiește implicit billingAddress. Configurarea firmei/seriei este citită în același merchant/store; modificarea conexiunii cere reverificare. Snapshot-ul fiscal și completările persistate sunt pasul separat 09.2a.2.2, cu protecție la schimbarea comenzii/profilului; raportul actual are canIssue=false permanent. Gestiunea stocului rămâne amânată conform D04.

## Reguli acceptate — 2026-09-30

D01 — reguli de produs acceptate: diferența de preț la schimb se introduce manual, fără compensare automată. Bifa Transport preia tariful configurat în site și facturează pe datele inițiale ale clientului; AWB se generează automat cu COD egal cu diferența manuală plus transportul selectat. Nimic/total zero la schimb sau retrimitere înseamnă fără factură nouă/COD 0; regula nu elimină factura unei comenzi obișnuite deja plătite.

Storno automat numai la înregistrarea unui refuz de primire, pe original verificat; returul obișnuit nu declanșează storno. Facturile externe se acceptă numai după identificare/verificare în Oblio; original neverificabil blochează storno. Refuzul repetat nu dublează storno; rezultat necunoscut cere reconciliere conform D11. Planul nu avea un modul distinct pentru refuzuri: fluxul este păstrat în backlog-ul 16, corelat cu tracking/recepție, fără implementarea lui acum.

D04 — gestiunea stocului, reintegrarea, ajustările și rezervările sunt amânate explicit pentru o zonă separată. Oblio va emite fără modificări de stoc; citirea disponibilității rămâne separată. Email și SPV fără trimitere automată în etapa de test, acceptat. Mediul de emitere D08, validarea fiscală și probele reale de emitere/storno rămân restante. Aceste decizii actualizează propunerile istorice de mai jos; nu certifică providerul.

Reluare: 09.2a.2 — date complete și lista lipsurilor; 09 rămâne singurul modul activ. Schimbare numai documentară; fără emitere sau teste runtime rerulate.


Actualizat: 2026-09-30. `CERINȚĂ` provine din brief/utilizator; `PROPUS` nu înseamnă aprobat. Pentru acceptare se notează data și decizia utilizatorului, fără a presupune aprobarea din lipsa unui răspuns.

## Stabilite prin cerințe

| ID | Decizie | Motiv și consecință |
| --- | --- | --- |
| R01 | CERINȚĂ — PHP + MySQL; HTML/CSS/JS nativ | Nu adoptăm React/Vue/Angular |
| R02 | CERINȚĂ — ONE CORE + multiple connectors/providers | Tipurile furnizorilor rămân în adaptoare |
| R03 | CERINȚĂ — V1 Shopify, Sameday, FAN, Oblio | Celelalte integrări sunt backlog |
| R04 | CERINȚĂ — Un singur portal și launcher | CMS-urile nu duplică UI-ul de retur |
| R05 | CERINȚĂ — Un modul activ, teste și documentare | Nicio dezvoltare paralelă a trei module; progres în Git |
| R06 | CERINȚĂ — Arhitectura înainte de aplicație | Brief §51 cere oprire după plan până la aprobare |
| R07 | CERINȚĂ — Credentials/IBAN criptate, tenant isolation și audit | Intră de la fundație, nu ca remediere la final |
| R08 | CERINȚĂ — Outbound: selecție business și COD automat | Nimic = fără factură/COD 0; produse/transport se calculează |

## Propuneri structurale ale planului

Direcția A01–A06 a fost acceptată pentru continuare prin mesajul „CONTINUA”; alegerea detaliilor de implementare se consemnează separat. Tabelul păstrează propunerile de arhitectură ca referință, iar D01–D08 au stările de mai jos.

| ID | Stare | Alegere și consecință |
| --- | --- | --- |
| A01 | PROPUS | Monolit modular, module cu Domain/Application/Infrastructure/Presentation; deployment comun inițial |
| A02 | PROPUS | MySQL comun cu merchant/store context și FK-uri compuse; nu DB separată per merchant în V1 |
| A03 | PROPUS | Outbox + inbox + jobs MySQL, Operation pentru pași externi; fără event sourcing |
| A04 | PROPUS | ReturnCase gestionează inbound; ExchangeCase îl referă; OutboundPlan pregătește Shipment fără duplicarea AWB-ului |
| A05 | PROPUS | Bani în unități minore, snapshots imutabile după emitere și quotes versionate |
| A06 | PROPUS | Provider timeout ambiguu cere reconciliere; nu promitem exactly-once la un API care nu o permite |

## D01 — Politică de facturare/storno pentru outbound

Stare: REGULI DE PRODUS ACCEPTATE, 2026-09-30, conform secțiunii de sus. Validarea fiscală și probele providerului rămân înainte de operare.

Nimic/total zero la schimb → fără factură nouă/COD 0. Diferență introdusă manual, transport din tariful site-ului pe datele inițiale; suma lor determină factura și COD. Storno automat numai la înregistrarea refuzului de primire, pe original verificat (inclusiv original extern verificat în Oblio); nu la retur obișnuit. Acestea sunt reguli de produs acceptate, nu concluzii de conformitate fiscală.

## D02 — Executarea rambursării

Stare: DESCHIS. Termen: înainte de 17.

Propunere V1: Ordely pregătește coada și datele; comerciantul plătește extern; finance confirmă suma, referința și data. Nu există integrare bancară cerută explicit în brief. Dacă se dorește plata automată, se adaugă Payment/RefundProvider și un modul dedicat. Nu folosim „REFUNDED” doar fiindcă operatorul a deschis ecranul sau s-a emis storno.

## D03 — Momentul outbound și colet la schimb

Stare: DESCHIS. Termen: înainte de 13/18/19.

Brief-ul cere READY_FOR_OUTBOUND la recepție. Confirmăm dacă inspecția este obligatorie înainte de expediere. „Colet la schimb” poate colecta inbound odată cu livrarea outbound; este o rută alternativă care trebuie definită cu providerul. Nu colectăm din nou un produs deja recepționat. Capabilitățile reale FAN/Sameday se confirmă pe cont/serviciu.

## D04 — Stoc și rezervări

Stare: STABILIT PENTRU CITIRE ÎN 08; gestiunea stocului/rezervările/scrierile AMÂNATE explicit la 2026-09-30 pentru o zonă separată.

Citirea per locație și revalidarea disponibilității sunt separate de gestiunea stocului. Nu rezervăm, nu ajustăm și nu reintegrăm produse în fluxul curent; emiterea Oblio trebuie configurată fără scrieri de stoc. Snapshot-ul de stoc nu este rezervare.

Implementare 08: citire per locație, available negativ/null distinct și observedAt; fără nicio scriere de inventar. Revalidarea și selecția înlocuitorului rămân în 18.

## D05 — Identificare client și OTP

Stare: DESCHIS. Termen: înainte de 14.

Brief-ul cere ID comandă + email/telefon și suport OTP configurabil. Alegem default-ul, TTL-ul și acțiunile care cer verificare mai puternică. Recomandare: OTP înainte de expunerea datelor sensibile; gateway-ul și costul se aleg la implementarea portalului. Public store token nu este secret și nu autorizează comenzi.

## D06 — Runtime și infrastructură

Stare: ALES PENTRU FUNDAȚIA DE DEZVOLTARE, 2026-09-27. Hosting-ul producției și worker supervisor se decid înainte de pilot.

Alegere tehnică în continuarea autorizată: PHP 8.4.24, MySQL 8.4.11, Composer 2.10.3, PSR-4, Symfony 7.4 HttpFoundation/Routing/Dotenv, PHPUnit 12.5, PHPStan 2 level 8. Fără framework frontend sau dependențe Symfony în Domain. Patch-urile sunt fixate în composer.lock/runtime images. Docker Compose este mediul comun; helper-ul Windows pornește MySQL separat de XAMPP. CI verifică Linux cu MySQL/HTTP și PHP pe Windows. Comenzi și surse în [setup](setup.md).

## D07 — Limite V1 și operare

Stare: DESCHIS. Termen: valorile care influențează setup-ul înainte de 02; restul înainte de pilot.

De stabilit: volume zilnice și vârfuri, număr stores/tenant, țări, RON-only versus multimonedă, produse fracționare, SLO-uri, backup/restore, retenție pentru date/payload/audit și limite fișiere. Propuneri: RON pentru operațiunile V1, cantități întregi și păstrarea monedei originale la import, fără conversie implicită. Billing abonament SaaS este separat de facturarea comenzilor.

Notă D07 pentru modulul 02: mediul este local, pe loopback, fără dimensionare de producție sau promisiuni de performanță. Porturi implicite 8080/33060. Reproductibilitatea se verifică într-un checkout CI independent; accesul la al doilea PC personal nu este presupus.

## D08 — Conturi, furnizori și distribuție

Stare: DESCHIS. Termen: înainte de integrarea fiecărui provider și înainte de lansare.

Sunt necesare contul developer/dev store Shopify, scopes/PCD, credențiale de test, documentația și serviciile contractate Sameday/FAN, profilul/seriile Oblio, operațiile suportate pentru storno și lookup. Strategia de distribuție/pilot și monetizare poate cere un modul de SaaS billing; nu confundăm InvoiceProvider pentru comercianți cu taxarea abonamentului Ordely. Disponibilitatea sandbox-urilor și a funcțiilor nu a fost verificată în conturi reale.

## D09 — Identitate și sesiuni (03)

Ales 2026-09-27: token aleator de 256 biți, hash SHA-256 în DB, TTL 8 ore, membership verificat la fiecare cerere, cookie HttpOnly/SameSite=Lax/Secure pe HTTPS și CSRF pentru scrieri. Parole bcrypt prin PASSWORD_DEFAULT pe PHP fixat, cu limită explicită 12–72 bytes. Rate limit atomic 20/cont și 100/IP în 15 minute. Provisionare CLI de încredere; onboarding/resetare prin email în 20. Migrații SQL simple cu checksum și marker `applying`, fiindcă DDL MySQL nu se poate proteja prin rollback tranzacțional obișnuit. Surse: [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php), [MySQL implicit commits](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html), [Symfony HttpFoundation](https://symfony.com/doc/7.4/components/http_foundation.html).

## D10 — Money și contracte (04)

Ales 2026-09-27: integer 64-bit cu limită ±9e15 unități minore, exponent monedă explicit, reprezentare JSON string, rotunjire half-away-from-zero și alocare prin resturi maxime cu ordine stabilă. Porturi mici compuse de CommerceConnector, plus CarrierProvider/InvoiceProvider; DTO-uri imutabile fără SDK. ProviderFailure separă Transient (efect cert neprodus) de Unknown (reconciliere). Fakes sunt exclusiv simulatoare în memorie; nu validează API-uri reale sau politici fiscale. D01–D05 rămân deschise.

## O01 — Continuitate Git

Stare: CONFIGURAT ȘI PUBLICAT.

Remote furnizat: `https://github.com/razvanstav/ordely.git`, configurat ca `origin`. Verificarea remote-ului nu a returnat branch-uri. Branch local: `codex/modul-01-arhitectura`.

Inițial identitatea Git nu era configurată, iar utilizatorul a amânat configurarea. Ulterior a cerut explicit push. Contul GitHub autentificat a fost verificat; identitatea `razvanstav` și adresa noreply bazată pe ID-ul contului au fost configurate numai pentru acest repository. Primul commit `3a08834` a fost publicat, iar hash-ul remote a fost verificat identic cu HEAD local. Reluarea pe alt PC folosește același branch și documentele din Git; proba efectivă pe al doilea PC rămâne de făcut.

## D11 — Operațiuni durabile (05)

Ales 2026-09-28: MySQL queue cu index ordonat, SKIP LOCKED, token/fencing și lease 60 secunde; retry cu jitter și Retry-After, implicit 5 încercări, plafon 25 pentru jobs. Coordonator extern cu intenție stabilă, cheie provider stabilă, maximum 5 apeluri certe temporare; UNKNOWN nu se repetă automat. Confirmare după reconciliere cu versiune și digest dovadă, auditată. Payload allowlist numai referințe/counters/digest, fără date personale. Migrarea 003 remediază problema de locking demonstrată cu două procese. Detalii în [operations](operations.md).
## D12 — Conexiuni și criptare (06)

Ales 2026-09-28: AES-256-GCM din OpenSSL deja disponibil, cheie aleatoare 32 bytes/nonce 12/tag 16; keyring separat de DB/Git, AAD cu merchant/conexiune/provider/key ID și versiunea formatului. Rotație prin fișier nou care păstrează cheile vechi, apoi recriptare versionată inclusiv pentru arhive revocate; inventar înainte de retragerea cheilor. Owner/admin cu all_stores gestionează secretele comune merchant-ului. Registry/fabrici în composition root, capabilities din adaptor, simulatoare numai dev/test. Providerii reali, setările specifice și autorizarea lor rămân în modulele respective. Surse și operare: [integrations](integrations.md).

## D13 — Mediu și autentificare Shopify (07)

Ales 2026-09-28: magazin separat **Ordely Shop (dev)** și aplicație **Ordely**, create în Dev Dashboard. Utilizatorul a exclus explicit ANATOMIK live din integrare/teste. Dev allowlist obligatoriu în backend; secretele rămân în fișiere ignorate/criptate, fără chat/Git.

API stabil `2026-07`, confirmat și în Dev Dashboard. Direcție embedded cu App Bridge, instalare gestionată de Shopify, token exchange PHP și offline `expiring=1`, fără framework JS. ID token validează identitatea; drepturile Ordely se verifică separat. Scopes inițiale goale; comenzi/catalog și PCD numai la funcționalitățile care le cer. URL HTTPS temporar furnizat de CLI; instalare, token exchange, refresh, uninstall și reinstalare confirmate exclusiv pe Ordely Shop (dev).

Surse oficiale: [ID tokens](https://shopify.dev/docs/apps/build/authentication-authorization/id-tokens), [token exchange/refresh](https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens), [expirare offline](https://shopify.dev/docs/apps/build/authentication-authorization/migrate-to-expiring-offline-access-tokens), [webhook HMAC](https://shopify.dev/docs/apps/build/webhooks/verify-deliveries). D08 rămâne deschis pentru distribuție/PCD și fluxurile comerciale viitoare.

D13 — completare implementare 07: cod bearer Ordely aleator 256 biți, hash și TTL 10 minute, asociere unică persistentă shop/merchant/store; instalările nu se mută prin binding generic. Expiring offline tokens criptate împreună cu refresh/expiry/scopes; serializare MySQL și commit al tokenului nou chiar dacă proba următoare eșuează temporar. API 2026-07 și zero scopes verificate real. Uninstall real revocă atomic prin inbox lifecycle separat, cu timestamp pentru evenimente întârziate. Cererile privacy rămân needs_review și cer procedură/implementare înainte de date personale/pilot. Operațiile CommerceConnector rămân în 08.

D08 — mediul de test și credențialele aplicației sunt configurate; instalare/refresh/uninstall reale confirmate. Distribuția/PCD/lansarea rămân deschise. Shopify cere și acces standard la datele proprietarului; utilizatorul a finalizat personal Install după explicarea acestui acces. Reinstalarea, noua conexiune și refresh-ul sunt confirmate; blocarea anterioară este rezolvată. [Operare și limite](shopify.md).

## D14 — Import și proiecții (08)

Ales 2026-09-28 în continuarea autorizată: port Core ImportSource pentru citiri normalizate, separat de comenzile și snapshoturile simplificate pentru facturare/fulfillment din 04. Păstrăm faptele financiare Shopify exact, inclusiv reduceri/taxe/rambursări și cantități diferite, fără forțarea unei formule de factură. Schema comună de proiecții JSON normalizate are coloane indexate pentru identitate externă unică, parent, ID intern, tenant/store, versiune și observare; liniile comenzii au ID intern stabil. Nu importăm obiecte Shopify în Core.

Staging pe run, un request limitat la 25 elemente/job, commit atomic numai după toate paginile, hash canonic și outbox ORDER_IMPORTED. Două start-uri concurente se unifică; lease-ul și conexiunea se verifică inclusiv înainte de commit. Comenzile folosesc watermark cu suprapunere cinci minute; catalogul se reconciliază complet. Reconcilierea este la cerere, fără scheduler/webhookuri comerciale deocamdată. Schimbarea comenzii între pagini cere restart explicit; nu amestecăm versiuni.

Comenzile/staging sunt criptate integral; inventarul de chei include toate bucățile. Privacy locală permite export, confirmarea separată a livrării răspunsului, redact și prevenirea reimportului. Staging abandonat expiră după șapte zile; publicarea/restart îl șterge imediat. Retenția datelor reale/backup, distribuția și aprobările Shopify de producție rămân deschise înainte de pilot.

Scopes aplicației sunt read_orders/read_products/read_inventory/read_locations, confirmate real pe Ordely Shop. Utilizatorul a aprobat ulterior write_orders pentru CLI și fixture, dar auto-review a blocat instalarea Shopify CLI Connector App pentru confirmarea explicită a datelor personale incluse (clienți și proprietar). Detaliile UI și documentația Shopify au fost verificate; cerința de confirmare rămâne. Nu s-a executat scrierea; modulul rămâne REVIEW până la proba reală a comenzii. Detalii și surse: [import](commerce-import.md), [raport](testing/08-commerce-import.md).
## D15 — Continuare fără login Shopify

2026-09-28, autorizat explicit: „Hai să continuăm fără testele de Shopify. Că nu pot să mă loghez.” Probele reale dependente de cont din 08 sunt amânate, nu marcate PASS sau DONE. Introducem DEFERRED_EXTERNAL, care eliberează unicul loc activ; continuăm 09 în pași locali cu teste automate offline. Nu eliminăm testele simulate existente, nu ocolim refuzul instalării CLI și nu pretindem integrare reală validată. D01/D08 rămân deschise pentru emitere/storno; pregătirea ciornelor nu alege reguli fiscale și nu trimite documente către provider.

## D16 — Ciorne locale de facturi (09.1)

Ales 2026-09-28 în continuarea D15: pas local independent de CMS/provider, cu document editabil și revizii criptate. Reutilizăm Money/Core CommercialLine pentru calcule; taxa totală pe linie este introdusă explicit, fără rată fiscală presupusă. Owner/admin/finance editează, operator citește, viewer nu are acces; scope merchant/store se revalidează. Crearea folosește ID stabil și comparație canonică, editarea/arhivarea cer versiunea curentă. Audit/outbox atomic conține numai referințe. Istoricul păstrează cheile vechi în inventar.

Ciorna nu constituie model complet de emitere și nu primește număr fiscal. Adresa/datele emitentului/seriile și validarea fiscală se completează înaintea adaptorului și emiterii. D01/D08 rămân deschise; totalul zero într-o ciornă nu decide politica de facturare. Limite și API în [invoicing](invoicing.md).

## D17 — Reluarea probei 08 și fixture prin token offline

2026-09-29. Utilizatorul a cerut reluarea Shopify după login și a autorizat explicit, în două răspunsuri, CLI Connector App/write_orders/datele personale afișate și preview-ul Ordely cu write_orders temporar. Refuzurile auto-review anterioare au fost depășite prin aceste acorduri; nu se repetă întrebările pentru aceeași destinație și același scop.

orderCreate este permis numai cu offline token; store auth/execute CLI oferă online. Proba CLI a eșuat fără creare, lookup-ul ulterior fiind gol. Testul aprobat continuă prin tokenul offline al aplicației Ordely după legarea autentică App Bridge. Fixture: o singură comandă sintetică de 30 linii, test=true, PENDING, fără notificări/plăți și inventory BYPASS; lookup înaintea creării și reconciliere înainte de orice retry ambiguu. Write_orders se retrage după creare, iar importul/reimportul se verifică numai cu citire. Nu extindem scope-urile aplicației în configurația publicată în Git și nu folosim ANATOMIK live. [Probe și surse](testing/08-commerce-import.md).

08 este unicul modul REVIEW; 09 PAUSED la finalul pasului 09.1. Revine activ numai după finalizarea sau reamânarea explicită a probei 08.

D17 — continuare autonomă, 2026-09-29: utilizatorul a autorizat automatizările și accesul necesar în Ordely dev și a cerut închiderea mediului la blocaj. Autentificarea oficială client_credentials pentru propria organizație a permis crearea unică a fixture-ului 8239905505617 și două citiri live complete. Această metodă a fost folosită numai de helper-ele locale de probă; nu extindem autentificarea produsului și nu pretindem că validează App Bridge. Perechea access/refresh a aplicației nu a fost fabricată. Importul/reimportul persistat rămâne de verificat după conectarea autentică.

Corecție de scope necesară modulului 08: read_customers se adaugă permanent în configurația de citire, deoarece query-ul existent order.customer.id a fost refuzat real fără el. ID-ul clientului este folosit și în corelarea privacy. Accesul la datele clienților în Ordely dev este deja autorizat. [Customer](https://shopify.dev/docs/api/admin-graphql/latest/objects/Customer) confirmă cerința. write_orders a fost retras după creare; cinci scope-uri exclusiv de citire verificate înainte și după app dev clean. Configurația publicată nu acordă scriere. Browserul indisponibil rămâne blocaj tehnic, nu acord restant. Detalii și limite în raportul 08.

D17 — închidere pe PC-ul inițial, 2026-09-29: după fetch/pull la bea3206, conexiunea App Bridge autentică din 07 era păstrată local și refresh-ul ei a reușit (v4→v5, cinci scope-uri de citire). Nu este necesară o nouă legare dacă această conexiune validă există. Import/reimport HTTP + worker au trecut: 30 linii/36,00 RON, criptare DB/staging, paginare 25+5, publicare atomică, ID-uri/versiuni/hash stabile și un singur ORDER_IMPORTED. Fixture-ul nu s-a recreat. 08 DONE; 09.2 se reia separat. Git transferă codul și dovezile, nu conexiunea sau cheile fiecărui PC. [Dovezi finale](testing/08-commerce-import.md).

D01/D08 — pregătire 09.2, 2026-09-29: după „ok”, 09 redevine unicul IN_PROGRESS. Propunerea de produs și întrebarea privind contul/mediul Oblio sunt formulate în docs/oblio-integration.md și trimise utilizatorului; răspunsurile încă lipsesc. D01 rămâne DESCHIS (produs și validarea responsabilului fiscal), D08 DESCHIS pentru Oblio. Continuarea nu acordă implicit emitere pe producție. Criteriile 09.2a–c sunt definite înainte de cod; nu se modifică aplicația până la fixarea alegerilor care afectează implementarea. Verificarea API-ului este documentară, distinctă de o integrare reală.

## D18 — Conexiune Oblio locală și citire înaintea modelului fiscal

2026-09-29: utilizatorul confirmă contul Oblio și cere pregătirea integrării locale, indicând tabul Chrome autentificat. Prioritizăm 09.2b în același modul 09, independent de D01. Reutilizăm credentialele criptate/binding/versioning din 06 și adăugăm un port neutru pentru configurația de facturare. Adaptorul real declară numai citirea configurației; celelalte metode InvoiceProvider sunt explicit Unsupported. Core nu importă Oblio.

Nu salvăm încă profilul de emitere, nu presupunem sandbox și nu executăm operații asupra documentelor, stocului, emailului sau SPV. Listele sunt citite la cerere, compania este validată în cont înaintea seriilor/TVA, iar rolul și contextul conexiunii se revalidează după rețea. Tokenul temporar nu este persistat; emailul/cheia API rămân în infrastructura de criptare, după salvarea prin UI. Încercarea inițială cu valori mascate din browser nu a creat o conexiune; nu se cer chei în chat.

D18 — verificare reală, 2026-09-30: utilizatorul a furnizat datele API și a confirmat emailul contului. Salvarea prin UI, asocierea magazinului și citirea reală au reușit: 1 firmă, 1 serie de factură și 10 cote TVA. Persistarea criptată și auditul numai cu versiune/număr de rezultate au fost verificate în DB. 09.2b este finalizat; configurația și secretele rămân locale, fără valori reale în predare/Git. Nu se recreează conexiunea validă la reluarea pe același PC.

D01 rămâne deschis; D08 este acum verificat pentru autentificare și citirea contului. Firma/seria și condițiile probelor fiscale rămân de stabilit înainte de 09.3. Citirea nomenclatoarelor nu salvează un profil fiscal și nu dovedește emitere/idempotency/storno validate.

## D19 — Gruparea interfeței existente în modulul 09

2026-09-29, cerere explicită de simplificare și design: utilizatorul nu se poate orienta în panoul care afișează toate funcțiile simultan. Reorganizăm interfețele deja implementate în același modul 09, înainte de proba Oblio. Meniul separă activitățile zilnice, configurarea și diagnosticul; Acasă arată numai numărători disponibile și pași bazați pe configurația locală. Formularele și detaliile tehnice se deschid la cerere. Conexiunile salvate sunt distincte de accesul verificat.

Implementarea rămâne HTML/CSS/JavaScript nativ, cu navigare prin fragment URL, controale semantice, focus și aranjare adaptivă. API-urile, drepturile și contractele Core sunt păstrate; nu adăugăm funcții comerciale, onboarding complet sau agregări noi din modulul 20. Acesta rămâne PLANNED, iar 09 unicul IN_PROGRESS. Probele UI folosesc datele sintetice locale și nu emit documente sau sincronizări noi. [Rezultate](testing/09-invoicing.md).

## D20 — Acces personal în mediul local de dezvoltare

2026-09-30: utilizatorul a ales explicit emailul/parola pentru Ordely local, clarificând că nu sunt credentiale Oblio. Contul este creat administrativ în merchant-ul local existent, ca owner cu acces la magazinele sale; contul sintetic și integrările sunt păstrate. Parola aleasă sub limita provisionării standard este acceptată punctual pentru acest cont de dezvoltare, ca hash bcrypt. Validatorul general, expirarea sesiunilor, rate limiting și CSRF nu sunt schimbate. Nu se introduce un login fără parolă sau un endpoint de provisionare public.

Înainte de orice deployment nou se înlocuiește această parolă cu una conformă politicii standard și se revizuiesc conturile de dezvoltare. O DB de dezvoltare locală nu garantează izolarea rețelei dacă există un tunel de preview; nu declarăm un deployment privat din faptul că GitHub este privat. Hosting-ul PHP/MySQL și accesul său se stabilesc separat; nicio publicare nouă nu a fost executată în acest pas. Datele de acces rămân în afara Git și a documentelor de predare.

Clarificare finală: utilizatorul nu are hosting și a ales explicit continuarea locală. Nu pregătim un deployment nou în acest pas.

## D21 — Catalog de integrări separat de configurarea serviciului

2026-09-30, cerere explicită: utilizatorul găsește interfața greu de citit și cere separarea facturării de curieri, cu opțiuni clare la apăsarea pe Oblio. Catalogul grupează serviciile pe activitate; ecranul serviciului selectat arată numai conexiunile și opțiunile sale. Contul existent se deschide pentru gestionare, iar crearea unui cont suplimentar este o acțiune distinctă. Secțiunile, stările și listele de serii/TVA au text lizibil; configurările tehnice sunt închise implicit. Curierii planificați sunt prezentați ca indisponibili, fără implementarea modulelor 10/11. Extinderea de prezentare rămâne în modulul 09; 20 nu este început. Criterii și rezultate în fișa/raportul 09.

D21 implementat și verificat: desktop/mobil, navigare, formulare separate, citire reală Oblio și regresie 220 teste PASS. Simulatoarele nu apar în catalog/formularul real, rămân disponibile numai prin infrastructura de dezvoltare existentă. Starea „cont salvat” nu pretinde acces live la fiecare reload; verificarea accesului este la cerere. Profilul și alegerea fiscală rămân în pasul următor.

## D22 — Configurare locală a firmei și seriei pe magazin

2026-09-30: utilizatorul confirmă continuarea cu configurarea facturării. Descompunem 09.2a în pași: primul persistă numai firma/seria verificate din contul asociat magazinului. Acest profil nu este încă snapshot-ul complet al emitentului și nu validează întreaga factură. Core rămâne neutru, iar API-ul de salvare reverifică nomenclatoarele; nu acceptă numele firmei din browser. Datele fiscale sunt criptate și legate de merchant/store/conexiune/versiuni prin AAD. Un profil per magazin, CAS și retry identic; audit fără conținut fiscal. Modificarea versiunii/asocierii conexiunii cere reverificarea profilului. Nu alegem implicit TVA, opțiuni stoc/email/SPV sau politica D01; nicio emitere. Administrarea folosește permisiunile conexiunilor existente; citirea respectă invoices.read și granturile magazinului. Datele complete ale ciornelor și pregătirea emiterii rămân în 09.2a, fără închiderea întregului modul.
