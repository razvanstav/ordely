# Instrucțiuni de lucru — Ordely

## Înainte de orice modificare

1. Citește `STATUS.md`, `plan.md`, fișa modulului curent și deciziile relevante din `docs/decisions.md`.
2. Verifică `git status --short --branch`, branch-ul și remote-ul. Protejează modificările existente.
3. La reluare pe alt PC, urmează `docs/workflow.md`; nu presupune că checkout-ul local este actualizat.
4. Citește cerințele relevante din `docs/brief-original.md` și `docs/architecture.md`.

## Un singur modul activ

- Lucrează exclusiv la modulul desemnat în `plan.md`; `IN_PROGRESS`, `REVIEW` și `BLOCKED` ocupă același unic loc activ.
- Nu implementa în paralel alte module și nu delega module către alți agenți.
- Împarte modulul activ în pași mici, verificabili. Păstrează lucrările viitoare în backlog.
- Rezolvă în același modul doar dependențele necesare obiectivului său; consemnează extinderile de scop.
- Nu începe implementarea aplicației înainte de aprobarea arhitecturii de către utilizator, conform brief-ului §51.
- Un modul poate avea mai multe conversații. La încheierea conversației, lasă un punct exact de reluare.
- Nu crea automat conversații noi. Pregătește în fișa modulului textul de reluare.

## Reguli tehnice

- PHP + MySQL; frontend HTML/CSS și JavaScript nativ, fără React/Vue/Angular.
- Core-ul definește contractele; connectorii și providerii le implementează. Core-ul nu importă SDK-uri sau tipuri Shopify ori ale altor furnizori.
- Contextul merchant/store se validează la fiecare operațiune, inclusiv jobs, webhook-uri și descărcări.
- Banii folosesc unități monetare minore și monedă explicită; fără `float` pentru calcule monetare.
- Efectele externe trebuie să aibă idempotency, audit și tratarea rezultatului necunoscut după timeout.
- Nu salva secrete, IBAN-uri reale, date personale reale sau payload-uri de producție în Git ori în documentele de predare.

## Verificări și documentare

- Definește criteriile de acceptare înainte de implementare; rulează verificările proporționale cu modificarea și testele obligatorii ale modulului.
- Notează comanda efectiv executată, mediul, rezultatul și limitările. Separă `PASS`, `FAIL`, `NOT_RUN` și `BLOCKED`.
- Un test planificat nu este un test trecut. Un mock nu confirmă funcționarea unui provider real.
- Nu marca un modul `DONE` cu teste obligatorii eșuate sau neexecutate ori criterii restante.
- Pentru o schimbare numai de documentație, verifică documentele; nu inventa teste runtime.
- După fiecare pas semnificativ și obligatoriu înainte de a încheia sesiunea: actualizează fișa modulului, `STATUS.md`, `plan.md`, jurnalul și deciziile afectate.
- `plan.md` este registrul stărilor modulelor; `STATUS.md` este rezumatul de predare. Dacă diferă, rezolvă discrepanța verificând fișierele și istoricul Git.

## Git și predare

- Codul și documentația aferentă se salvează împreună în commit-uri mici și descriptive când identitatea Git este configurată.
- Branch-urile noi folosesc prefixul `codex/`; continuă branch-ul existent dacă acolo este modulul activ.
- Nu inventa numele/emailul autorului Git sau URL-ul remote-ului.
- Folosește push numai către destinația stabilită pentru proiect. Verifică rezultatul; nu afirma sincronizare fără push reușit.
- Nu șterge modificări, nu folosi `reset --hard` sau force push pentru a rezolva predarea.
- Dacă sincronizarea nu este posibilă, salvează local tot ce se poate și raportează precis ce lipsește.
- Încheie cu: ce s-a schimbat, ce s-a verificat, ce rămâne și punctul următor de reluare.
