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
- [x] Identitate verificată prin contul GitHub autentificat; commit/push reușite și hash remote verificat.
- [x] Verificare finală a consistenței documentelor — PASS în raport; 30 secțiuni, linkuri valide, un modul activ, brief identic.
- [ ] Aprobarea arhitecturii conform brief-ului §51.

Documentele sunt publicate pe GitHub. Commit/push au fost verificate; clonarea și reluarea efectivă pe al doilea PC rămân neexecutate și nu sunt prezentate ca teste trecute.

## Verificări

Raport: [01-architecture-review.md](../testing/01-architecture-review.md). Nu există aplicație asupra căreia să ruleze teste runtime. Verificarea planului nu este validare a API-urilor furnizorilor.

## Decizii și fișiere

- [Arhitectură](../architecture.md), [decizii D01–D08 și O01](../decisions.md).
- [Workflow](../workflow.md), [stare](../../STATUS.md), [plan](../../plan.md), [jurnal](../journal/2026-09-27.md).

## Predare

- Ultimul pas: redactarea și verificarea planului și a documentelor de predare.
- Următorul pas: revizuirea arhitecturii cu utilizatorul, mai întâi direcția și D06/D07 pentru modulul 02.
- Branch: `codex/modul-01-arhitectura`. Remote: `origin`, cu upstream configurat. Primul commit publicat: `3a08834`; sincronizare verificată.
- Modulul 02 nu este început. Nu se începe aplicația până la aprobarea arhitecturii.

Prompt de reluare:

> Continuă numai modulul 01 Ordely. Citește AGENTS.md, STATUS.md, plan.md, această fișă și docs/decisions.md. Verifică branch-ul și sincronizarea cu origin înainte de schimbări. Revizuim arhitectura înainte de implementare; actualizează documentele după orice decizie nouă.
