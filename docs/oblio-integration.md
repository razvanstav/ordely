# Pregătirea integrării Oblio — 09.2

2026-09-29. Document de implementare înainte de cod; modulul 09 este unicul IN_PROGRESS. 09.1 rămâne funcțional. Nu există încă adaptor Oblio sau probă de cont real.

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
2. **09.2b — conexiune și nomenclatoare:** secrete criptate, autentificare, alegerea companiei/seriei/tratamentelor TVA, validare merchant/store. Primele probe reale sunt numai de citire, după stabilirea contului D08.
3. **09.2c — adaptor și mapare:** contract InvoiceProvider, corp de cerere din snapshot validat, răspuns normalizat, fără float; opțiuni externe explicite și capability numai pentru ce este susținut. Testele de transport controlat nu sunt probe live.
4. **09.3 — execuție durabilă:** emitere, rezultat necunoscut/reconciliere, storno și PDF; probe controlate pe un cont/mediu identificat. Nu combinăm acest pas cu modulul 10.

## Criterii înainte de închiderea 09.2

- [ ] D01 are o regulă explicită pentru zero la outbound, compensare, momentul storno și originalele externe.
- [ ] Datele complete și erorile de pregătire sunt vizibile; vechile ciorne nu sunt completate cu valori inventate.
- [ ] Tenant/store/conexiune/profil se verifică la API, persistare și apelul adaptorului; credentialele nu ajung în UI/loguri.
- [ ] TVA și reducerile au reprezentare exactă; monede incompatibile, cote lipsă, overflow și diferențe de total sunt respinse.
- [ ] Contul și profilul/seriile D08 sunt identificate; nomenclatoarele reale sunt verificate separat de testele simulate.
- [ ] Erorile auth/429/5xx/timeout/răspuns invalid au mapare sigură; nu repetăm automat o scriere ambiguă.
- [ ] Teste unitare, contracte și integrare pe MySQL, HTTP/CSRF/roluri, compatibilitate documente, criptare și concurență PASS; UI verificată proporțional.
- [ ] Documente, CI și Git actualizate; emiterile reale și limitările rămân raportate separat.

## Deciziile cerute utilizatorului

**D01, propunere neacceptată încă:** numai pentru schimb/retrimitere, selecție cu total zero → fără factură nouă/COD 0; fără compensarea automată a prețului returului cu înlocuitorul; storno numai prin acțiune explicită, pe original verificat. Facturile externe se referă numai după verificarea lor; o referință neverificabilă blochează storno. Regula nu spune că o comandă obișnuită plătită, cu COD 0, nu primește factură. Regula de produs nu închide validarea fiscală din D01.

**D08, informație lipsă:** cont cu firmă/serie pentru teste sau doar cont de producție; separat trebuie confirmate setările efective de stoc, email și SPV. Cheile se introduc local prin configurația securizată care va fi livrată, nu în chat/Git. Până la răspuns continuă doar analiza/modelarea care nu presupune aceste alegeri; nu există apeluri Oblio autentificate în această etapă.
