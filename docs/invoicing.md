# Ciorne de facturi — pasul 09.1

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