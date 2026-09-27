# Modul 01 — Arhitectură și continuitate

Actualizat: 2026-09-27. Stare în [plan](../../plan.md): **REVIEW**.

## Obiectiv

Transformarea brief-ului într-un plan implementabil și într-un workflow care poate fi reluat în conversații separate, de pe mai multe PC-uri. Acesta este singurul modul activ.

## Inclus și exclus

Inclus: arhitectură, domain model, state machines, contracte, schemă logică MySQL, API propus, securitate, ordine de implementare, strategie de testare, reguli de documentare și legătura Git remote.

Exclus: codul aplicației, migrări executabile, conturi Shopify/curieri/facturare, instalări de runtime, deployment și implementarea modulelor următoare.

## Pași și criterii

- [x] Brief citit și păstrat integral în repository.
- [x] Repository și mediul local inspectate fără suprascrierea unei aplicații existente.
- [x] Cele 30 de livrabile de arhitectură redactate în ordinea cerută.
- [x] Reguli din brief separate de propuneri și decizii deschise.
- [x] Un singur modul activ; 20 module de implementare rămân PLANNED.
- [x] AGENTS, plan, stare, template și workflow pentru conversații separate.
- [x] Remote-ul furnizat de utilizator configurat și branch-ul modulului pregătit.
- [x] Verificare finală a consistenței documentelor — PASS în raport; 30 secțiuni, linkuri valide, un modul activ, brief identic.
- [ ] Aprobarea arhitecturii conform brief-ului §51.

Sincronizarea efectivă este restantă și amânată de utilizator: fără identitate Git nu există commit/push. Această limitare este vizibilă în STATUS și nu se prezintă ca test trecut de lucru pe al doilea PC.

## Verificări

Raport: [01-architecture-review.md](../testing/01-architecture-review.md). Nu există aplicație asupra căreia să ruleze teste runtime. Verificarea planului nu este validare a API-urilor furnizorilor.

## Decizii și fișiere

- [Arhitectură](../architecture.md), [decizii D01–D08 și O01](../decisions.md).
- [Workflow](../workflow.md), [stare](../../STATUS.md), [plan](../../plan.md), [jurnal](../journal/2026-09-27.md).

## Predare

- Ultimul pas: redactarea și verificarea planului și a documentelor de predare.
- Următorul pas: revizuirea arhitecturii cu utilizatorul, mai întâi direcția și D06/D07 pentru modulul 02.
- Branch: `codex/modul-01-arhitectura`. Remote: `origin`. Fără commit/push; identitatea a fost lăsată neconfigurată la cererea utilizatorului.
- Modulul 02 nu este început. Nu se începe aplicația până la aprobarea arhitecturii.

Prompt de reluare:

> Continuă numai modulul 01 Ordely. Citește AGENTS.md, STATUS.md, plan.md, această fișă și docs/decisions.md. Revizuim arhitectura înainte de implementare. Păstrează vizibilă starea locală nesincronizată a Git și actualizează documentele după orice decizie nouă.
