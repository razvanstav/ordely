# Pregătirea integrării Oblio — 09.2

## Rezultat local — 09.3b.1, 2026-10-01

OblioInvoiceResponse parsează offline receipt-ul de creare, apoi un răspuns separat de listare în firma/seria/numărul cererii. Receipt-ul singur nu confirmă totalul. Se cere un singur document, ID numeric, număr păstrat exact, tip Factura, stare emis/neanulat, moneda/precizia/datele cererii, stoc dezactivat și suma exactă. Zerourile suplimentare de precizie se elimină fără rotunjire; diferențele, ambiguitatea și numeric float sunt UNKNOWN. Referința este oblio:ID; URL-urile și payload-ul brut sunt ignorate. [Documentația oficială](https://www.oblio.eu/api) confirmă forma răspunsurilor, nu integrarea în cont.

Coordonatorul local salvează contractul neutru verificat, criptat, atomic cu confirmarea Operations; nu fabrică InvoiceSnapshot din totalul trimis. Allowlist-ul OblioTransport și metodele de scriere Unsupported nu s-au schimbat. În 09.3b.2 rămân transportul și lookup-ul efectiv, cu validarea firmei/nomenclatoarelor/contextului, apoi conectarea UI. Probe reale amânate D26; original/storno/PDF și extinderi fiscale încă restante.

## Mapper local — 09.2c.1, 2026-10-01

OblioInvoiceMapper map-ează InvoiceDraft fiscal după reconcilierea InvoiceAssembly, fără transport. Cere companyId/nume/serie identice profilului și o singură cotă din nomenclator cu procentul ales. TVA standard, RON/2 zecimale; scutirile/în afara sferei ori cotele ambigue sunt Unsupported/Validation înainte de efect. Baza importată este păstrată; reducerile valorice sunt imediat după produs, discountAllAbove=0. Clientul provine numai din billing, fără shipping/CNP/IBAN/email ori încasare presupusă; date calendaristice și cheie idempotentă explicite.

Mapperul fixează useStock/sendEmail/spvExtern și save pentru produs/client la 0, autocomplete=0. Nu activează capability de emitere și nu schimbă allowlist-ul de transport. Viitoarea operație durabilă trebuie să revalideze actor/context/conexiune/profil, să recitească nomenclatoarele și să confirme opțiunile D08 înainte de transmitere. Testele offline nu confirmă calculul/efectele contului. [Documentația oficială](https://www.oblio.eu/api) consultată documentar la 2026-10-01; probe reale amânate D26.

## Pregătire din comanda CMS — 09.2a.2.1

Disponibilă 2026-09-30 din Comenzi → Pregătește facturarea. GET /api/invoice-preparation/{orderId}?storeId=... folosește exclusiv datele locale importate și profilul firmei/seriei, cu invoices.read + orders.read și merchant/store/granturi verificate în tranzacție. Raportul păstrează prețurile și taxele exact, fără transformare implicită în net sau cotă TVA; adresa de livrare nu substituie facturarea. issues conține code/path/message, source indică ID/versiune/momentul observării. Profil schimbat, date lipsă, comenzi modificate și monede/precizii incompatibile sunt semnalate. canIssue=false întotdeauna; acest raport nu autorizează emitere și nu modifică o ciornă. UI poate fi consultată și de operator/finance, fără acces la credentiale.

Nu se apelează Oblio, nu se generează factură, nu se scrie stoc. Urmează 09.2a.2.2 pentru completări persistate și snapshot fiscal, apoi maparea 09.2c. Datele CMS actuale nu includ tipul fiscal al clientului/CUI, unitatea și cota TVA explicită; câmpurile se vor completa numai dacă lipsesc, fără reintroducerea datelor existente. [Probe](testing/09-invoicing.md).

## Reguli acceptate — 2026-09-30

D01 — reguli de produs acceptate: diferența de preț la schimb se introduce manual, fără compensare automată. Bifa Transport preia tariful configurat în site și facturează pe datele inițiale ale clientului; AWB se generează automat cu COD egal cu diferența manuală plus transportul selectat. Nimic/total zero la schimb sau retrimitere înseamnă fără factură nouă/COD 0; regula nu elimină factura unei comenzi obișnuite deja plătite.

Storno automat numai la înregistrarea unui refuz de primire, pe original verificat; returul obișnuit nu declanșează storno. Facturile externe se acceptă numai după identificare/verificare în Oblio; original neverificabil blochează storno. Refuzul repetat nu dublează storno; rezultat necunoscut cere reconciliere conform D11. Planul nu avea un modul distinct pentru refuzuri: fluxul este păstrat în backlog-ul 16, corelat cu tracking/recepție, fără implementarea lui acum.

D04 — gestiunea stocului, reintegrarea, ajustările și rezervările sunt amânate explicit pentru o zonă separată. Oblio va emite fără modificări de stoc; citirea disponibilității rămâne separată. Email și SPV fără trimitere automată în etapa de test, acceptat. Mediul de emitere D08, validarea fiscală și probele reale de emitere/storno rămân restante. Aceste decizii actualizează propunerile istorice de mai jos; nu certifică providerul.

Reluare: 09.2a.2 — date complete și lista lipsurilor; 09 rămâne singurul modul activ. Schimbare numai documentară; fără emitere sau teste runtime rerulate.


2026-09-30. Modulul 09 este unicul IN_PROGRESS. 09.1 și 09.2b sunt păstrate. 09.2a.1 salvează criptat firma/seria verificate pentru fiecare magazin; proba reală de salvare/reload PASS. Datele complete ale emitentului/destinatarului, liniile/TVA și operațiile fiscale din restul 09.2a/c–09.3 rămân de implementat. [Probe](testing/09-invoicing.md).

## Ce trebuie completat în model

Ciorna 09.1 are destinatar/adresă text și taxă valorică pe linie. Aceste date nu determină singure profilul de emitere sau cota TVA. Nu deducem o cotă din raportul taxă/net și nu transformăm automat adresa liberă într-o adresă verificată.

Core InvoiceDraft primește deja seller/customer/serie/linii, dar CustomerSnapshot este orientat spre commerce, iar CommercialLine nu exprimă unitatea de măsură sau tratamentul taxei. În 09.2 definim date de facturare neutre, fără câmpuri sau SDK Oblio în Core; adaptăm explicit contractele și fake-ul existent. Ciornele salvate înainte de extindere trebuie să rămână citibile și editabile, cu o listă clară de completări necesare pentru emitere.

| Grup | Date de pregătit și verificate |
| --- | --- |
| Emitent | Profil cu denumire, identificare fiscală, adresă structurată, conexiune și serie verificate pentru același merchant/store |
| Destinatar | Persoană fizică/juridică, nume, adresă de facturare structurată; identificare fiscală și statut TVA unde se aplică; email opțional separat de trimitere |
| Document | Dată emitere/scadență, monedă explicită, referință internă și versiunea ciornei; curs/configurație explicită înainte de emitere în valută |
| Linie | ID stabil, descriere, cantitate, unitate, preț net, reducere și tratament TVA selectat; total calculat exact pe server |
| Referințe | Intenție unică de emitere; pentru storno, original verificat și alocări explicite de linii/sume |

Nu colectăm CNP, IBAN sau date de delegat implicit. Profilurile și snapshot-urile folosesc criptarea existentă și inventarul cheilor. Selectarea unei firme ori serii de la un provider nu autorizează alt merchant/store.

## Contractul extern verificat documentar

API-ul oficial documentează autentificare cu email/client_secret și token temporar, citirea companiilor/seriilor/cotelor TVA și emitere cu idempotencyKey. Emiterea are opțiuni pentru email, stoc și SPV, iar salvarea prețului unui produs este implicit activă. Vom seta explicit opțiunile potrivite fluxului Ordely; o serie numită TEST nu dovedește izolarea mediului. [Documentație oficială](https://www.oblio.eu/api).

Pagina publică nu explică durata/domeniul cheii idempotente sau reconcilierea după răspuns pierdut și nu declară clar un sandbox. La consultarea din 2026-10-01 există un exemplu de storno total prin referenceDocument de tip Factura; refund privește eliminarea încasării asociate, nu transferul de bani către client. Această mapare și verificarea originalului rămân în continuarea 09. Exemplele PHP oficiale confirmă folosirea cheii la creare; nu dovedesc comportamentul contului utilizatorului. [API oficial](https://www.oblio.eu/api), [exemple Oblio](https://github.com/OblioSoftware/OblioApi).

## Pași verificabili

1. **09.2a — model și verificarea pregătirii:** date neutre, validatori, compatibilitate cu ciornele 09.1, UI cu erori precise. Fără emitere externă. D01 se fixează înainte de codul care aplică politica.
2. **09.2b — conexiune și nomenclatoare, finalizat:** secrete criptate, autentificare, selecția companiei pentru citirea seriilor/TVA, validare merchant/store. Proba reală numai de citire PASS la 2026-09-30. Persistarea profilului/seriei și selecția tratamentelor TVA pentru emitere aparțin pasului următor.
3. **09.2c — adaptor și mapare:** contract InvoiceProvider, corp de cerere din snapshot validat, răspuns normalizat, fără float; opțiuni externe explicite și capability numai pentru ce este susținut. Testele de transport controlat nu sunt probe live.
4. **09.3 — execuție durabilă:** emitere, rezultat necunoscut/reconciliere, storno și PDF; probe controlate pe un cont/mediu identificat. Nu combinăm acest pas cu modulul 10.

## Criterii înainte de închiderea 09.2

- [x] D01 are reguli de produs acceptate la 2026-09-30 pentru zero la outbound, diferență manuală, transport, storno la refuz și originalele externe; validarea fiscală rămâne înainte de operare.
- [ ] Datele complete și erorile de pregătire sunt vizibile; vechile ciorne nu sunt completate cu valori inventate.
- [ ] Tenant/store/conexiune/profil se verifică la API, persistare și apelul adaptorului; credentialele nu ajung în UI/loguri.
- [ ] TVA și reducerile au reprezentare exactă; monede incompatibile, cote lipsă, overflow și diferențe de total sunt respinse.
- [x] Contul D08 este autentificat și nomenclatoarele reale sunt verificate separat de testele simulate (2026-09-30).
- [ ] Profilul, seria și mediul de emitere D08 sunt stabilite; citirea unei serii existente nu înlocuiește această alegere.
- [ ] Erorile auth/429/5xx/timeout/răspuns invalid au mapare sigură; nu repetăm automat o scriere ambiguă.
- [ ] Teste unitare, contracte și integrare pe MySQL, HTTP/CSRF/roluri, compatibilitate documente, criptare și concurență PASS; UI verificată proporțional.
- [ ] Documente, CI și Git actualizate; emiterile reale și limitările rămân raportate separat.

## Deciziile cerute utilizatorului

**D01, acceptat 2026-09-30:** numai pentru schimb/retrimitere, total zero → fără factură nouă/COD 0; diferență manuală plus transport selectat din tariful site-ului, facturate pe datele inițiale și folosite drept COD. Storno automat numai la înregistrarea refuzului de primire, pe original verificat; nu la retur obișnuit. Facturile externe se referă numai după verificarea în Oblio; o referință neverificabilă blochează storno. O comandă obișnuită plătită, cu COD 0, poate primi factură. Regulile de produs nu închid validarea fiscală din D01.

**D08:** contul este conectat și citirea reală verificată la 2026-09-30, după furnizarea datelor API și confirmarea emailului de către utilizator. Datele sunt salvate criptat în DB locală; nu se cer din nou și nu intră în Git/documente. Nu știm încă dacă firma/seria este pentru teste sau producție; separat trebuie confirmate setările efective de stoc, email și SPV înainte de emitere. Cotele returnate de cont nu sunt o alegere fiscală implicită.

## Operarea locală disponibilă — 09.2b

Pe PC-ul acestei probe există deja o conexiune Oblio activă și asociată magazinului. Integrări grupează separat Facturare, Curierat și Magazine online. Apasă Oblio din Facturare pentru ecranul `#integrations/oblio`: contul salvat, magazinul asociat și pașii pentru consultarea datelor. „Date de acces” și „Administrare și setări avansate” sunt închise implicit. Pașii de creare de mai jos sunt pentru un mediu nou sau acțiunea „Adaugă alt cont”; la reluare verifică mai întâi conexiunea existentă, fără să creezi o dublură. Git nu transferă DB sau keyring-ul.

1. Autentifică-te cu contul Ordely, apoi deschide Integrări → Facturare → Oblio. Pentru un cont nou, deschide formularul de conectare și introdu denumirea, emailul contului Oblio și cheia API din Oblio → Setări → Date cont. Parola de login Ordely nu înlocuiește cheia API Oblio. Nu este necesară regenerarea cheii.
2. Salvează; acest pas criptează datele în provider_connections și validează forma lor, fără să confirme autentificarea. Lista nu întoarce credentialele salvate. Înlocuirea și recriptarea folosesc versiunea conexiunii.
3. Asociază conexiunea magazinului local. Numai owner/admin cu acces la toate magazinele pot gestiona credentialele comune ale merchant-ului.
4. Apasă „Verifică accesul la Oblio”, alege explicit firma și apasă „Vezi seriile și cotele TVA”. Rezultatul apare în două liste distincte, cu marcaje pentru valorile implicite din Oblio; nu completează automat ciornele.
5. În pasul „Salvează configurarea facturării”, alege explicit seria și apasă „Salvează firma și seria”. Serverul reverifică firma/seria și conexiunea; rezumatul salvat apare în Oblio și Facturare. Alegerea implicită din Oblio nu devine automat alegerea magazinului. Conținutul este criptat; configurația nu este încă snapshot-ul complet al emitentului și nu atribuie numere fiscale.

## Configurație locală pe magazin — 09.2a.1

Migrația 008 adaugă `invoice_profiles`, unic pe merchant/store, legat prin FK de magazin/conexiune invoice. `GET /api/invoice-profile?storeId=...` întoarce `profile:null` sau firma/seria, versiunile și `needsVerification`. Citirea nu apelează providerul și respectă `invoices.read`/granturile; conținutul nu intră în audit. `POST /api/invoice-profile` primește storeId, connectionId, version (conexiune), expectedVersion (profil, 0 la prima salvare), companyId și series. Numele firmei vine numai din nomenclatorul verificat pe server. Necesită sesiune, CSRF/origin și administrarea conexiunilor owner/admin cu toate magazinele.

Salvarea reverifică versiunea/asocierea/rolul după rețea și serializează mutația locală pe magazin. CAS refuză o selecție concurentă diferită; repetarea identică a cererii cu aceeași versiune așteptată întoarce revizia deja salvată fără alt audit de salvare (citirea providerului poate fi repetată și auditată). Profilele sunt criptate AES-GCM cu AAD merchant/store/conexiune/versiuni; audit numai ID-uri/versiune. `bin/key-status.php` include `invoiceProfileKeyUsage`. Inventarul trebuie verificat înaintea retragerii oricărei chei. Modificarea/revocarea/dezasocierea conexiunii afișează `needsVerification`; se citește din nou și se salvează explicit. Schimbările făcute direct în Oblio nu sunt detectate automat la reload; nomenclatoarele se reverifică la salvare și vor trebui revalidate înaintea viitoarei emiteri.

Nu există încă profil complet de adresă/emitent, cote TVA selectate pe linii, snapshot legat de ciornă sau operație de emitere. Seria locală aleasă nu stabilește sandbox-ul ori autorizarea probelor fiscale D08. Continuarea este 09.2a.2 și D01 înaintea codului de politică.

POST /api/invoice-configuration primește storeId, connectionId, version și opțional companyId. Verifică sesiunea/CSRF/origin, rolul curent și legătura merchant/store/conexiune, înainte și după rețea. Compania trebuie să existe în lista contului înainte de solicitarea seriilor sale. Auditul păstrează numai versiunea și numărul de rezultate. Datele contului nu sunt scrise în audit sau loguri; răspunsul HTTP are no-store, iar UI șterge rezultatele la schimbarea contextului.

Transportul fixează HTTPS www.oblio.eu, fără redirects, cu TLS verificat, timeout 5 s conectare/20 s cerere și răspuns maxim 1 MiB. Numai autentificarea și cele trei citiri sunt admise. O citire cere un token nou, păstrat în memoria cererii; nu există polling sau retry automat. 401/403 devin o eroare de autentificare a providerului, 429/5xx/timeout/JSON invalid devin eroare temporară sigură; Retry-After numeric este propagat când există. Numerele JSON sunt conservate textual pentru TVA, maximum patru zecimale, fără alegerea unei cote legale pentru utilizator.

InvoiceProvider declară numai invoice_configuration; creare/anulare/storno/PDF/trimitere și citirea documentelor răspund Unsupported fără transport. Nu sunt implementate opțiunile stoc/email/SPV sau reconcilierea documentelor. Ciornele 09.1 nu se schimbă. [Probe și limite](testing/09-invoicing.md).
