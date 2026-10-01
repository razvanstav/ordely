# Ciorne de facturi — pasul 09.1

## Anulare pregătire și rezultat fiscal — 09.3b.1

POST /api/invoice-issue-intents/{id}/cancel primește strict storeId și expectedVersion (intentVersion, nu draftVersion). Owner/admin/finance cu sesiune/CSRF/origin/grant valide pot anula numai o intenție fără rezervare externă. Retry identic întoarce CANCELLED cu versiunea incrementată; context inaccesibil 403, input invalid 400 și rezervare/versiune incompatibilă 409. Snapshot-ul anulat rămâne în istoric. O nouă pregătire prin POST-ul existent citește revizia curentă și creează alt ID/cheie; nu înlocuiește snapshot folosit ori UNKNOWN. Lock-urile comune cu rezervarea garantează un singur câștigător în cereri concurente.

GET adaugă intentVersion, canCancel, cancelledAt, documentVerified și document (id/number/total minor/decimal/currency/exponent, creditNote/cancelled). Datele documentului sunt criptate în DB și disponibile numai rolurilor cu invoices.read și grant. documentVerified este true numai pentru confirmare cu document local verificat; o referință confirmată generic fără document nu certifică totalul. Ciorna/source/profilul pot evolua, iar rezultatul păstrează suma snapshot-ului emis.

Portul intern cere InvoiceSnapshot și persistă rezultatul valid atomic cu Operations/audit. Suma/moneda greșită, storno ori rezultat anulat devin UNKNOWN fără document. Eșecul DB la confirmare nu permite reemitere; expirarea lease-ului cere reconciliere. reconcile este intern, cu rezultat din citire verificată, expectedOperationVersion și evidenceHash; nu există endpoint care acceptă un document fiscal fabricat de browser. Fără schimbare UI sau apel real în acest lot. [D29](decisions.md), [probe](testing/09-invoicing.md).

## Intenție durabilă pentru factura inițială — 09.3a

POST /api/invoice-issue-intents primește strict storeId, orderId și expectedVersion integer (revizia ciornei CMS). Ciorna trebuie reconciliată și sursa/profilul/conexiunea curente. Pregătirea salvează o singură intenție per comandă cu snapshot fiscal criptat, separat de propunerea editabilă; o reluare identică întoarce același ID. Altă revizie produce 409, snapshot incomplet 400, context inaccesibil 403. Owner/admin/finance pregătesc, operator citește, viewer nu are acces; scrierile cer sesiune/CSRF/origin și granturi valide.

GET /api/invoice-issue-intents/{id}?storeId=ID întoarce doar metadatele: id/storeId/orderId/draftVersion, status PREPARED sau starea Operations, operationId/operationVersion/attempts/providerReference/createdAt și executionEnabled=false. Nu divulgă date fiscale, envelope, chei sau corpul cererii. Se poate consulta rezultatul și după eliminarea sursei CMS. Pregătirea nu creează un job, nu numără și nu emite factura; nu există buton nou sau rută de execuție în acest lot.

Portul intern IssueIntents.execute reconstruiește contractul din snapshot și reutilizează ExternalOperations cu cheie stabilă, retry numai la eșec cert temporar și UNKNOWN fără replay automat. Revalidează contextul, versiunea conexiunii și hash-ul/revizia ciornei înaintea apelului, fără rețea într-o tranzacție. Adaptorul real/răspunsul/documentul și anularea explicită a intenției netrimise sunt continuarea 09.3b; nu se suprascrie snapshot-ul după modificarea ciornei. [D28](decisions.md), [probe](testing/09-invoicing.md).

## Verificarea sumelor — 09.2c.1

La deschiderea ciornei CMS, GET întoarce reconciliation cu status INCOMPLETE/MISMATCH/RECONCILED, totals net/tax/discount/gross și issues code/path/message. Calculele se fac pe snapshot-ul salvat; nu apelează furnizori și nu modifică revizia. UI arată net/taxe/total și diferențele alături de lipsurile fiscale. readyForProvider înseamnă că se poate construi contractul neutru, nu că Oblio a fost verificat sau documentul se poate emite; canIssue=false. SourceChanged oprește pregătirea până la refresh.

Prețul unitar × cantitatea se compară cu totalul inițial, reducerile alocate cu subtotalul, TVA-ul importat cu alegerea explicită, apoi totalurile cu suma liniilor. Nu există ajustare de un ban. Baza cu/fără taxe rămâne cea din CMS; nu este fabricat un net unitar rotunjit. Taxele cu componente multiple, transportul fără linie fiscală completă și monedele nesuportate sunt semnalate. Ciornele incomplete rămân salvabile; reconcilierea nu stabilește plata ori rambursarea.

OblioInvoiceMapper pregătește doar corpul cererii, cu nomenclator verificat și unic, TVA standard și reduceri alocate fiecărei linii. Scrierile reale sunt refuzate de provider și transport; nu există endpoint de emitere activ în acest lot. [Contract](core-contracts.md), [limite/probe](testing/09-invoicing.md).

## Ciornă CMS cu completări fiscale — 09.2a.2.2b

În Facturare, deschide o ciornă din comenzi după salvarea propunerii CMS. Completează datele documentului, câmpurile lipsă ale clientului/adresei, adresa și statutul TVA ale emitentului și unitățile/tratamentul TVA pentru produse. Valorile comune se aplică explicit tuturor produselor fără excepție; excepțiile sunt legate de ID-ul produsului. Unitatea poate fi schimbată separat, dar o excepție TVA are tratament/cotă sau motiv proprii. Cotele se trimit ca text zecimal cu punct, maximum patru zecimale, fără calcule float. Poți salva o completare parțială. Datele importate apar numai de citire.

PUT /api/invoice-order-drafts/{id}: storeId, expectedVersion integer, details obiect. Grupuri: document (issuedOn/dueOn YYYY-MM-DD), seller (adresă structurată, vatStatus), customer (lipsuri nume/adresă, type, taxId pentru firmă, vatStatus), lineDefaults (unit/treatment/rate/reason), lines (listă de excepții cu ID și aceleași câmpuri). Tipuri: individual/company; TVA registered/not_registered/not_applicable unde se aplică; tratament standard/exempt/outside_scope. Cheile necunoscute, sume/firma/seria ori înlocuirea datelor importate sunt respinse. Limita HTTP este 16 KiB; pentru multe excepții cu texte lungi se reduce corpul cererii.

GET al ciornei adaugă fiscal cu document/client/emitent/linii/issues și readyForMapping. READY_FOR_MAPPING înseamnă câmpuri pregătite pentru pasul de reconciliere/mapare; canIssue=false. Nu este o factură validată sau emisă. Ciorna și completările sunt criptate împreună; aceeași salvare repetată nu dublează revizia. SourceChanged blochează completarea până la actualizarea explicită și salvarea din CMS. Refresh păstrează completările aplicabile, elimină excepțiile produselor dispărute și acordă prioritate noilor date importate.

Owner/admin/finance salvează, operator citește, viewer este refuzat; context/granturi/CSRF se verifică pe server. Niciun apel extern sau efect fiscal. [D26 și probele finale](testing/final-integrations.md).

Ciornele se pregătesc în Ordely pentru orice magazin, inclusiv „Manual / test”. Nu cer conexiune Shopify sau Oblio. Acest pas nu emite documente fiscale, nu atribuie serii/numere, nu trimite emailuri și nu modifică plăți ori stocuri.

## Utilizare

În panoul „Ciorne de facturi”, selectează magazinul și „Ciornă nouă”. Completează referința internă, destinatarul și liniile. Adresa și codul fiscal sunt opționale în această etapă. „Calculează totalul” verifică sumele pe server fără salvare. „Salvează ciorna” păstrează o revizie criptată; „Deschide” permite editarea ei. „Arhivează” închide ciorna pentru editare și o mută în filtrul Arhivate. Nu există ștergere sau restaurare în 09.1.

Owner, admin și finance pot crea/modifica/arhiva; operator poate citi; viewer nu are acces. Permisiunile merchant/store și granturile se revalidează pe server, inclusiv după schimbarea rolului. Detaliile sunt auditate. Lista conține referință, total și număr de linii, fără numele/adresa destinatarului.

## Sume și limite

RON/EUR, cantități întregi între 1 și 1.000.000, 1–50 de linii cu ID stabil. Prețul net unitar, reducerea netă pe întreaga linie și taxa totală pe linie sunt șiruri zecimale cu maximum două cifre după separator. UI acceptă punct sau virgulă și trimite punct în API. Serverul folosește Money și numai unități minore întregi.

`net = preț unitar × cantitate − reducere`; `total = suma neturilor + suma taxelor`. Reducerile peste valoarea liniei, sumele negative, float, depășirile Money, ID-urile duplicate și câmpurile necunoscute sunt respinse. Exemplu sintetic: `10.10 × 2 − 0.20 + 3.00 = 23.00 RON`. Nu deducem rata TVA; taxa se introduce explicit. Un total zero poate exista într-o ciornă, fără să stabilească eligibilitatea de emitere din D01.

Corpul HTTP este limitat la 16 KiB, iar documentul canonic stocat la 24.000 bytes. Limita de 50 de linii nu elimină limita de dimensiune; descrierile lungi pot cere un document mai mic. Listele au 25 de rezultate/pagină, ordonate după ID, cu cursor.

## API local

Toate cererile cer sesiune Ordely și storeId. Scrierile cer JSON, CSRF și origin acceptat. ID-urile sunt hex lowercase de 32 caractere; valorile monetare rămân string, cantitatea și versiunea sunt integer.

| Cerere | Corp / scop |
| --- | --- |
| POST /api/invoice-drafts/preview | storeId, document; calcul fără persistare |
| POST /api/invoice-drafts | id, storeId, document; creare idempotentă |
| GET /api/invoice-drafts?storeId=…&status=DRAFT | Listă; status ARCHIVED și after opționale |
| GET /api/invoice-drafts/{id}?storeId=… | Detalii și versiune curentă |
| PUT /api/invoice-drafts/{id} | storeId, version, document; revizie nouă |
| POST /api/invoice-drafts/{id}/archive | storeId, version; arhivare |

Document: reference, customerName, customerAddress și customerTaxId opționale, currency, lines. Fiecare linie: id, description, quantity, unitNet, discountNet, tax. Totalurile nu sunt acceptate de la client. La aceeași creare cu același ID și document canonic, răspunsul reutilizează ciorna. Un document diferit, versiune învechită sau ciornă arhivată produce 409; redeschide ciorna înainte de editare. Repetarea unei editări nu generează revizii suplimentare cu aceeași versiune.

## Persistență și chei

Migrația 007 separă metadatele de reviziile criptate AES-256-GCM. AAD leagă documentul de merchant, store, draft și versiune. Cheile externe DB/Git sunt aceleași din modulul 06. Modificarea, revizia, auditul și outbox-ul intră într-o singură tranzacție. Evenimentele INVOICE_DRAFT_CREATED/UPDATED/ARCHIVED conțin numai ID și versiune; nu au consumator de emitere în acest pas și nu apelează provideri.

Istoricul este păstrat inclusiv după arhivare. `php bin/key-status.php` inventariază invoiceDraftKeyUsage pentru toate reviziile. Schimbarea cheii active afectează reviziile noi; nu recriptează istoricul. Păstrează cheile vechi cât timp sunt referite, inclusiv în backupuri; un utilitar de recriptare a istoricului nu este livrat în 09.1.

Git sincronizează codul, migrațiile, testele și predarea. Ciornele și conturile locale sunt în DB; keyring-ul și .env nu sunt în Git. Pe alt PC, aplică migrațiile și configurează mediul potrivit. Pentru aceleași date trebuie restaurată separat baza de date împreună cu keyring-ul corespunzător, prin procedura sigură de backup; simplul pull nu transferă datele.

## Reluare

09.2 va valida datele complete ale emitentului/destinatarului, D01 și conexiunea/adaptorul Oblio. DraftDocument este o pregătire editabilă; nu este încă un Core InvoiceDraft complet pentru provider. 09.3 va gestiona emitere/storno/reconciliere/PDF. [Fișa](modules/09-invoicing.md), [testele](testing/09-invoicing.md), [deciziile](decisions.md).

## Ciornă inițială din comandă CMS — 09.2a.2.2a

Din Comenzi → Pregătește facturarea, „Salvează ciorna din comandă” păstrează raportul curent chiar dacă lipsesc date fiscale. Facturare arată lista separată „Ciorne din comenzi”, cu 25 rezultate/pagină. Deschide snapshot-ul salvat, iar „Actualizează din CMS” încarcă datele actuale; salvarea actualizării este un pas explicit. Schimbarea comenzii/profilului/conexiunii este semnalată la deschidere. Nu primește număr fiscal și canIssue rămâne false.

GET /api/invoice-order-drafts?storeId=…&after=… listează propunerile; GET /api/invoice-order-drafts/{orderId}?storeId=… întoarce draft sau null. POST pe același ID acceptă numai storeId, expectedVersion (0 la creare), orderVersion și profileVersion (0 fără profil). Serverul recitește datele și respinge versiunea sursei/profilului schimbată (409); nu acceptă sume/adrese din browser. Retry identic păstrează revizia, alte actualizări cer versiunea curentă. Owner/admin/finance salvează, operator citește, viewer refuzat; sunt necesare invoices.read, orders.read și la salvare invoices.draft, cu granturile magazinului.

Migrația 009 salvează o singură propunere revizuibilă pentru factura inițială a comenzii, separată de registrul viitoarelor documente. Snapshot-ul este criptat în chunk-uri, cu AAD distinct pentru context și versiuni; `invoicePreparationKeyUsage` inventariază cheile folosite. Persistarea și auditul de salvare sunt atomice, fără apel extern. Sincronizarea nu suprascrie snapshot-ul; ștergerea sursei CMS elimină această propunere ne-fiscală prin FK CASCADE pentru privacy. Nu se aplică automat aceeași politică viitoarelor documente fiscale emise (D24). Completările fiscale persistate urmează în 09.2a.2.2b.
