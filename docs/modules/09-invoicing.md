# Modul 09 — Facturare

2026-09-29. **IN_PROGRESS**, unicul modul activ. 09.1 este păstrat; 09.2b implementat local după confirmarea contului Oblio. Citirea contului real așteaptă introducerea credențialelor în UI.

## Pas 09.1 — ciorne locale

- [x] Ciornă independentă de CMS/provider: referință, destinatar, adresă/date opționale și 1–50 linii, RON/EUR explicit.
- [x] Bani numai integer minor units; preț net unitar × cantitate − reducere netă + taxă introdusă explicit; total calculat pe server, fără rată fiscală presupusă.
- [x] Schema versionată, conținut criptat și revizii; separare merchant/store și permisiuni owner/admin/finance pentru editare, operator doar citire, viewer fără acces.
- [x] Creare repetată fără duplicare; editare/arhivare cu versiune și concurență reală; audit/outbox atomic fără date personale.
- [x] UI/API creare, calcul, listare, detalii, editare, arhivare; nicio emitere externă și niciun număr fiscal atribuit.
- [x] Teste unitare, MySQL, roluri/granturi/CSRF, HTTP real, criptare, rollback și două procese concurente; CI și Git verificate pentru predarea pasului.

09.1 finalizat: 207 teste locale PASS, 213 lint, PHPStan 8, JS și HTTP. Codul f622aab este publicat cu hash remote verificat și [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). [Operare](../invoicing.md), [raport](../testing/09-invoicing.md). Inspecția vizuală manuală este NOT_RUN; nu se confundă cu probele HTTP.

## Pas curent — 09.2b, conexiune locală și citire

### Pas UI cerut de utilizator, 2026-09-29

Înainte de continuarea conectării, utilizatorul cere gruparea și simplificarea ecranului actual. Extindere explicită de scop în 09: reorganizăm suprafețele deja implementate, fără funcțiile viitoare ale modulului 20. Criterii înainte de cod: navigare clară cu o singură zonă principală vizibilă, vedere de ansamblu cu pași/acțiuni reale, separare activități zilnice/configurare/diagnostic, formulare și opțiuni avansate dezvăluite la cerere, vocabular accesibil, desktop/mobil fără overflow, focus/tastatură și roluri păstrate. Verificăm în browser navigarea, facturarea și traseul de conectare fără apeluri externe sau salvarea unor credențiale reale; rulăm verificările de sintaxă/HTTP și regresie relevante. Modulul 09 rămâne unicul IN_PROGRESS.

- [x] Șase pagini cu meniu persistent, URL/back și focus: Acasă, Comenzi și catalog, Facturare, Magazine, Integrări, Activitate sistem.
- [x] Acasă cu numere din API-ul local și ghid bazat pe magazine/conexiuni asociate; conexiunea salvată nu este prezentată ca probă de acces real.
- [x] Formulare Shopify/Oblio la cerere; setări avansate și conexiuni dezactivate în detalii închise; activitate filtrată implicit la ce necesită atenție.
- [x] Design adaptiv și etichete în română; o singură pagină vizibilă și fără overflow pe toate cele șase pagini la 390×844; tastatură/focus verificate în Chrome. Condițiile de rol sunt păstrate în cod; probele browser folosesc owner, nu pretind testarea vizuală a tuturor rolurilor.
- [x] Verificări: 220 teste, 222 lint, PHPStan 8, JS și 6 HTTP PASS; calcul de ciornă sintetică 12.00 RON fără salvare și fără apel extern. [Dovezi](../testing/09-invoicing.md), D19.

Pagina finală deschisă: Acasă. Formularul Oblio nu mai rămâne permanent pe ecran; se deschide din ghid sau Integrări → Oblio. Acest pas UI este finalizat; proba reală Oblio și restul modulului rămân restante.

Cod UI `b1709f0` publicat; hash local/remote identic, scanare staged fără secrete, linkuri relative și diff check PASS. [CI Windows/Linux 36575021341 PASS](https://github.com/razvanstav/ordely/actions/runs/36575021341). Actualizarea ulterioară este numai documentară.

### Implementarea conexiunii 09.2b

Utilizatorul confirmă un cont Oblio și autorizează pregătirea integrării locale. Începem cu 09.2b, independent de regulile D01 încă neconfirmate. Nu presupunem că firma deschisă în browser este sandbox. Acest pas nu include emitere, storno, email, stoc sau SPV și nu salvează încă profilul de emitere.

Criterii de acceptare înainte de cod: credentiale criptate prin infrastructura existentă; formulare dedicate email/cheie API; citire autenticată firme/serii de factură/cote TVA; contract neutru în Core; control merchant/store/rol/conexiune înainte și după apel; erori sigure auth/429/timeout/JSON invalid; nicio capabilitate de emitere; teste unitare/MySQL/HTTP și verificare UI. Proba reală de citire se raportează separat de transportul simulat. Datele reale și secretele nu intră în documentație/Git.

Implementat: port InvoiceConfigurationReader și rezultat neutru, adaptor Oblio cu transport HTTPS limitat, registry și validarea credentialelor înainte de persistare, API /api/invoice-configuration, audit fără datele firmei, formulare adaptate providerului și selecție de firmă numai pentru citire. Tokenul temporar rămâne în memoria cererii; TVA este text zecimal exact. Nicio migrație nouă și nicio salvare automată de profil fiscal.

Probe locale PASS: 220 teste (112 unit/768 assertions, 108 integration/664 assertions), 222 lint, PHPStan 8, JS și 6 HTTP. UI login/formular inspectate în Chrome. Proba reală rămâne NOT_RUN: citirea automatizată a câmpurilor Oblio furnizează valori mascate, iar validarea browserului a împiedicat salvarea. Formularul a fost golit; datele trebuie introduse direct în UI la continuarea conectării. 09.2b nu este declarat verificat live sau închis.

Cod `11dddf4` publicat, hash remote identic; [CI Windows/Linux 36551856411 PASS](https://github.com/razvanstav/ordely/actions/runs/36551856411).

## Pașii următori ai modulului, încă neimplementați

Pregătirea 09.2 este documentată în [planul integrării Oblio](../oblio-integration.md): lipsurile modelului actual, etape 09.2a–c și criterii înainte de cod. Contul și pregătirea locală sunt confirmate; regulile D01 și firma/seria/mediul de emitere D08 încă lipsesc. „ok” nu este interpretat ca acceptare a regulilor detaliate care nu fuseseră prezentate încă.

09.2: validarea datelor complete pentru emitere și a regulilor D01, conexiune/adaptor Oblio și schema sa. 09.3: emitere/storno/reconciliere/PDF prin operații durabile, contract tests și proba controlată a contului. Contul și seriile D08 se stabilesc înainte de probe externe.

09 nu este DONE după 09.1. Ciorna locală nu este document fiscal, nu declanșează rambursare/storno și nu dovedește integrarea Oblio. Nu începem 10.

## Punct de reluare pe alt PC / în altă conversație

> Verifică Git și STATUS. 01–08 DONE; 09 este unicul IN_PROGRESS. UI este acum grupat în pagini (D19); pornește de la Acasă și feedbackul utilizatorului. 09.2b are 220 teste PASS, dar proba reală este NOT_RUN. Citește docs/oblio-integration.md și D01/D08/D16/D18. Pentru conectare, Integrări → Oblio; verifică dacă utilizatorul a salvat conexiunea, apoi asociază magazinul și citește firme/serii/TVA. Nu transfera valorile mascate din browser și nu cere chei în chat. Apoi fixează politica D01 și implementează 09.2a, păstrând ciornele vechi. Nu presupune sandbox/storno validate și nu emite documente din simpla confirmare a continuării. Nu începe 10 sau 20; actualizează testele, documentele și Git.
