# Modul 08 — Import comenzi și catalog

Stare: REVIEW, 2026-09-29, unicul activ. Utilizatorul a cerut reluarea probei Shopify după login și a acordat explicit permisiunile necesare. 09 este PAUSED la finalul 09.1; codul său rămâne publicat și verificat.

## Obiectiv și limită

Import reluabil, normalizat și izolat pe merchant/store din Ordely Shop (dev), folosind autentificarea 07 și jobs 05. Include comenzi/linii/bani/statusuri/client, produse/variante/opțiuni/stoc, reconciliere și UI. Exclude scrieri de stoc, rezervări, facturi, AWB și fulfillment. ANATOMIK live nu se folosește.

## Criterii de acceptare

- [x] Contract de import independent de Shopify și normalizare fără calcule monetare float.
- [x] Migrație 006 cu identitate externă unică, ID-uri interne stabile și FK compuse.
- [x] Query-uri validate, erori/rate limit și răspunsuri parțiale tratate explicit.
- [x] Paginare durabilă inclusiv linii, variante și locații; retry/restart, atomicitate și concurență testate.
- [x] Import repetat fără dubluri și ORDER_IMPORTED fără duplicare; watermark numai la succes.
- [x] Reconciliere catalog/stoc, cantități negative/necunoscute distincte; fără rezervări.
- [x] Izolare tenant/store/conexiune, sesiune/CSRF/roluri la API și guard în jobs înainte de commit.
- [x] Criptare comenzi/staging și inventarul cheilor; privacy export/confirmare/redact cu blocarea reimportului.
- [x] UI: start/progres/listare/detalii/paginare și privacy pentru operator autorizat.
- [x] Suite automate obligatorii: bani, paginare, concurență, retry, revocare, privacy și tenant isolation.
- [x] Catalog importat real de două ori pe Ordely Shop, fără dubluri sau versiuni nejustificate.
- [x] Cod publicat în Git și CI Windows/Linux PASS pentru `e8a4133` (run 36420254697).
- [ ] Comandă sintetică reală (>25 linii), verificare total, date client/PCD și reimport; **CLI autorizat, dar orderCreate cere token offline; preview Ordely autorizat și pornit, legarea locală încă așteptată**.
- [ ] Probele complete, CI, documente și Git verificate pentru închiderea definitivă a 08.

## Verificări

190 teste PASS, 204 lint, PHPStan 8, JS/config Shopify valide. Catalog real: 17 produse, 26 variante, 28 inventare. Raport complet și comenzile executate: [testing 08](../testing/08-commerce-import.md). Instrucțiuni/limite: [commerce-import](../commerce-import.md).

## Decizii și istoricul probei amânate

D04: numai citire pe locații în 08, rezervări înainte de 18. D14: proiecții separate de contractele de comenzi/facturare, staging atomic, retenție temporară șapte zile, privacy locală. Scopes de citire confirmate real; PCD pentru câmpurile unei comenzi necesită proba restantă.

Utilizatorul a acordat write_orders și crearea comenzii fictive. Auth CLI a deschis instalarea, dar auto-review a respins Install deoarece cere confirmarea explicită a datelor personale ale clienților/proprietarului afișate de Shopify. Detaliile permisiunilor și documentația oficială au fost verificate, fără eliminarea refuzului la reîncercare. Întrebarea exactă este în așteptare; conectorul nu este instalat și orderCreate nu s-a executat. Nu ocoli refuzul.

## Predare anterioară — înlocuită pentru continuarea curentă

Reluare pe PC-ul curent, 2026-09-28: fetch/pull până la `807e0da`, dependențe și migrații actualizate, suita completă 190 teste și HTTP smoke PASS. Preview local pornit. Lipsesc configurarea Shopify și contul sintetic local din predarea celuilalt PC; DB/tokenurile/keyring-ul lui nu sunt în Git. Rezolvă acest setup înainte de proba reală; aprobarea restantă pentru CLI Install nu a fost ocolită. Starea rămâne REVIEW.

- Ultimul pas: a treia rulare reală confirmă refresh automat după expirare (conexiune v4), catalog 17/26/28 fără dubluri; CLI Install blocat separat. CI Windows/Linux PASS pentru cod `e8a4133`.
- Următorul pas: confirmarea exactă a datelor din Install CLI numai pe dev store; apoi lookup fixture, creare unică, test real și închidere.
- Branch: codex/modul-01-arhitectura; codul și documentele publicate în `e8a4133`, urmate de predarea documentară cu rezultatul CI. Verifică Git la reluare.
- Reluare: „Continuă exclusiv modulul 08. Citește STATUS, raportul 08 și D14. Write_orders și comanda sunt aprobate; rezolvă confirmarea datelor personale cerută de auto-review la Install. Verifică întâi să nu existe deja ORDELY-TEST-M08. Nu începe 09.”
## Decizia anterioară — amânarea

Utilizatorul: „Hai să continuăm fără testele de Shopify. Că nu pot să mă loghez.” Nu se mai cere login și nu se reîncearcă instalarea CLI sau crearea comenzii acum. Proba celor 30 de linii/PCD/reimport rămâne NOT_RUN/DEFERRED și se reia separat când accesul este disponibil. Următorul pas autorizat este partea locală a modulului 09. Rezultatele automate și cele trei importuri reale de catalog rămân dovezi istorice valide.

## Reluare curentă — 2026-09-29

Utilizatorul a autorizat explicit CLI Connector App și datele personale afișate. Autentificările CLI au reușit, dar orderCreate necesită token offline; CLI folosește online. Lookup ulterior confirmă zero fixture. Utilizatorul a autorizat separat actualizarea preview-ului Ordely și write_orders temporar pentru aceeași comandă, urmat de retragerea scrierii. Preview pornit; .env/keyring și cont sintetic locale, fără secrete în Git. Codul de legare există în var/shopify-link-code.txt, cu TTL 10 minute. Controlul browserului are eroare sandbox ACL; utilizatorul finalizează legarea în aplicația Shopify. Nu se modifică ANATOMIK live.

Urmează: legare autentică App Bridge; lookup înaintea creării unice prin token offline; retragere write_orders, refresh și verificare scope-uri; import complet/reimport, 30 linii, 36.00 RON estimat, date sintetice și criptare, ID-uri/evenimente stabile. Numai după aceste probe poate deveni 08 DONE.
