# Pregătirea integrării Oblio — 09.2

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

Pagina publică nu explică durata/domeniul cheii idempotente sau reconcilierea după răspuns pierdut. Nu am găsit acolo un endpoint de storno ori un sandbox declarat. Aceste absențe sunt limite ale verificării, nu dovada lipsei funcțiilor. Exemplele PHP oficiale confirmă folosirea cheii la creare; nu dovedesc comportamentul contului utilizatorului. [Exemple Oblio](https://github.com/OblioSoftware/OblioApi).

## Pași verificabili

1. **09.2a — model și verificarea pregătirii:** date neutre, validatori, compatibilitate cu ciornele 09.1, UI cu erori precise. Fără emitere externă. D01 se fixează înainte de codul care aplică politica.
2. **09.2b — conexiune și nomenclatoare, finalizat:** secrete criptate, autentificare, selecția companiei pentru citirea seriilor/TVA, validare merchant/store. Proba reală numai de citire PASS la 2026-09-30. Persistarea profilului/seriei și selecția tratamentelor TVA pentru emitere aparțin pasului următor.
3. **09.2c — adaptor și mapare:** contract InvoiceProvider, corp de cerere din snapshot validat, răspuns normalizat, fără float; opțiuni externe explicite și capability numai pentru ce este susținut. Testele de transport controlat nu sunt probe live.
4. **09.3 — execuție durabilă:** emitere, rezultat necunoscut/reconciliere, storno și PDF; probe controlate pe un cont/mediu identificat. Nu combinăm acest pas cu modulul 10.

## Criterii înainte de închiderea 09.2

- [ ] D01 are o regulă explicită pentru zero la outbound, compensare, momentul storno și originalele externe.
- [ ] Datele complete și erorile de pregătire sunt vizibile; vechile ciorne nu sunt completate cu valori inventate.
- [ ] Tenant/store/conexiune/profil se verifică la API, persistare și apelul adaptorului; credentialele nu ajung în UI/loguri.
- [ ] TVA și reducerile au reprezentare exactă; monede incompatibile, cote lipsă, overflow și diferențe de total sunt respinse.
- [x] Contul D08 este autentificat și nomenclatoarele reale sunt verificate separat de testele simulate (2026-09-30).
- [ ] Profilul, seria și mediul de emitere D08 sunt stabilite; citirea unei serii existente nu înlocuiește această alegere.
- [ ] Erorile auth/429/5xx/timeout/răspuns invalid au mapare sigură; nu repetăm automat o scriere ambiguă.
- [ ] Teste unitare, contracte și integrare pe MySQL, HTTP/CSRF/roluri, compatibilitate documente, criptare și concurență PASS; UI verificată proporțional.
- [ ] Documente, CI și Git actualizate; emiterile reale și limitările rămân raportate separat.

## Deciziile cerute utilizatorului

**D01, propunere neacceptată încă:** numai pentru schimb/retrimitere, selecție cu total zero → fără factură nouă/COD 0; fără compensarea automată a prețului returului cu înlocuitorul; storno numai prin acțiune explicită, pe original verificat. Facturile externe se referă numai după verificarea lor; o referință neverificabilă blochează storno. Regula nu spune că o comandă obișnuită plătită, cu COD 0, nu primește factură. Regula de produs nu închide validarea fiscală din D01.

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
