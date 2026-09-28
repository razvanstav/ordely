# Modul 09 — Facturare

2026-09-28. **PAUSED** la finalul 09.1; următorul pas 09.2. Utilizatorul a cerut reluarea probelor Shopify, astfel că 08 este temporar unicul activ. Progresul și CI 09.1 rămân păstrate.

## Pas 09.1 — ciorne locale

- [x] Ciornă independentă de CMS/provider: referință, destinatar, adresă/date opționale și 1–50 linii, RON/EUR explicit.
- [x] Bani numai integer minor units; preț net unitar × cantitate − reducere netă + taxă introdusă explicit; total calculat pe server, fără rată fiscală presupusă.
- [x] Schema versionată, conținut criptat și revizii; separare merchant/store și permisiuni owner/admin/finance pentru editare, operator doar citire, viewer fără acces.
- [x] Creare repetată fără duplicare; editare/arhivare cu versiune și concurență reală; audit/outbox atomic fără date personale.
- [x] UI/API creare, calcul, listare, detalii, editare, arhivare; nicio emitere externă și niciun număr fiscal atribuit.
- [x] Teste unitare, MySQL, roluri/granturi/CSRF, HTTP real, criptare, rollback și două procese concurente; CI și Git verificate pentru predarea pasului.

09.1 finalizat: 207 teste locale PASS, 213 lint, PHPStan 8, JS și HTTP. Codul f622aab este publicat cu hash remote verificat și [CI Windows/Linux PASS](https://github.com/razvanstav/ordely/actions/runs/36480973254). [Operare](../invoicing.md), [raport](../testing/09-invoicing.md). Inspecția vizuală manuală este NOT_RUN; nu se confundă cu probele HTTP.

## Pașii următori ai modulului, încă neimplementați

09.2: validarea datelor complete pentru emitere și a regulilor D01, conexiune/adaptor Oblio și schema sa. 09.3: emitere/storno/reconciliere/PDF prin operații durabile, contract tests și proba controlată a contului. Contul și seriile D08 se stabilesc înainte de probe externe.

09 nu este DONE după 09.1. Ciorna locală nu este document fiscal, nu declanșează rambursare/storno și nu dovedește integrarea Oblio. Nu începem 10.

## Punct de reluare pe alt PC / în altă conversație

> Verifică Git și STATUS înainte de reluare. 08 este unicul activ: fixture-ul există și citirea live este verificată, dar importul/reimportul persistat încă așteaptă App Bridge. Nu începe 09.2 până la închiderea sau reamânarea explicită a 08. Când 09 revine activ, citește fișa 09 și D01/D08/D15/D16, aplică migrațiile până la 007 și definește criteriile 09.2 pentru date complete de emitere și adaptor Oblio. Clarifică regulile fiscale înaintea emiterii. Nu începe 10; actualizează documentele, testele și Git la sfârșitul pasului.
