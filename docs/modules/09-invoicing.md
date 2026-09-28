# Modul 09 — Facturare

2026-09-28. **IN_PROGRESS**, unicul activ. 08 este DEFERRED_EXTERNAL prin cererea utilizatorului; predarea `ee8e3a6` este publicată. Lucrăm secvențial, fără Shopify live.

## Pas 09.1 — ciorne locale

- [x] Ciornă independentă de CMS/provider: referință, destinatar, adresă/date opționale și 1–50 linii, RON/EUR explicit.
- [x] Bani numai integer minor units; preț net unitar × cantitate − reducere netă + taxă introdusă explicit; total calculat pe server, fără rată fiscală presupusă.
- [x] Schema versionată, conținut criptat și revizii; separare merchant/store și permisiuni owner/admin/finance pentru editare, operator doar citire, viewer fără acces.
- [x] Creare repetată fără duplicare; editare/arhivare cu versiune și concurență reală; audit/outbox atomic fără date personale.
- [x] UI/API creare, calcul, listare, detalii, editare, arhivare; nicio emitere externă și niciun număr fiscal atribuit.
- [ ] Teste unitare, MySQL, roluri/granturi/CSRF, HTTP real, criptare, rollback și două procese concurente; CI și Git verificate pentru predarea pasului.

Implementarea 09.1 și verificările locale sunt finalizate: 207 teste PASS, 213 lint, PHPStan 8, JS și HTTP. Rămâne confirmarea CI după push. [Operare](../invoicing.md), [raport](../testing/09-invoicing.md). Inspecția vizuală manuală este NOT_RUN; nu se confundă cu probele HTTP.

## Pașii următori ai modulului, încă neimplementați

09.2: validarea datelor complete pentru emitere și a regulilor D01, conexiune/adaptor Oblio și schema sa. 09.3: emitere/storno/reconciliere/PDF prin operații durabile, contract tests și proba controlată a contului. Contul și seriile D08 se stabilesc înainte de probe externe.

09 nu este DONE după 09.1. Ciorna locală nu este document fiscal, nu declanșează rambursare/storno și nu dovedește integrarea Oblio. Nu începem 10.
