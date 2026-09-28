# Modul 08 — Import comenzi și catalog

Stare: REVIEW, 2026-09-28. Autorizat prin „Ok, te rog.”; unicul modul activ. 01–07 DONE, 09–21 PLANNED.

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
- [ ] Comandă sintetică reală (>25 linii), verificare total, date client/PCD și reimport; **blocată de lipsa acordului pentru write_orders CLI**.
- [ ] Probele complete, CI, documente și Git verificate pentru închiderea definitivă a 08.

## Verificări

190 teste PASS, 204 lint, PHPStan 8, JS/config Shopify valide. Catalog real: 17 produse, 26 variante, 28 inventare. Raport complet și comenzile executate: [testing 08](../testing/08-commerce-import.md). Instrucțiuni/limite: [commerce-import](../commerce-import.md).

## Decizii și blocaj

D04: numai citire pe locații în 08, rezervări înainte de 18. D14: proiecții separate de contractele de comenzi/facturare, staging atomic, retenție temporară șapte zile, privacy locală. Scopes de citire confirmate real; PCD pentru câmpurile unei comenzi necesită proba restantă.

Auto-review a respins acordarea write_orders către Shopify CLI: utilizatorul autorizase importul, nu explicit scrierea comenzilor. Fixture este pregătită și mutation validată, dar nu au fost executate auth sau orderCreate. Nu ocoli refuzul.

## Predare

- Ultimul pas: cod, teste și catalog real/reimport finalizate; UI verificată; CI Windows/Linux PASS, cod `e8a4133` publicat.
- Următorul pas: acord explicit pentru write_orders CLI numai pe dev store și o singură comandă sintetică; apoi test real, verificări și închidere.
- Branch: codex/modul-01-arhitectura; codul și documentele publicate în `e8a4133`, urmate de predarea documentară cu rezultatul CI. Verifică Git la reluare.
- Reluare: „Continuă exclusiv modulul 08. Citește STATUS, raportul 08 și D14. Acordul pentru write_orders trebuie să existe explicit înainte de comanda de test; după aceea verifică întâi să nu existe deja ORDELY-TEST-M08. Nu începe 09.”
