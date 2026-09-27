# Ordely

Platformă SaaS pentru operațiunile magazinelor online: un Core independent de CMS, conectat la platforme de comerț, curieri și servicii de facturare.

Runtime PHP/MySQL, instalare reproductibilă, CI Windows/Linux și identitate multi-tenant. Stadiul exact al fiecărui modul și următorul pas se păstrează în [STATUS](STATUS.md) și [plan](plan.md).

## De unde reluăm

1. Citește [regulile proiectului](AGENTS.md).
2. Citește [starea curentă](STATUS.md) și [planul modulelor](plan.md).
3. Citește fișa modulului activ indicată în STATUS; fiecare modul are predare proprie în `docs/modules/`.
4. Urmează [instrucțiunile de instalare și testare](docs/setup.md).
5. Consultă [arhitectura](docs/architecture.md) și [deciziile deschise](docs/decisions.md).

## Documentele proiectului

| Fișier | Rol |
| --- | --- |
| [Brief original](docs/brief-original.md) | Cerințele primite, păstrate integral |
| [plan.md](plan.md) | Ordinea modulelor și starea fiecăruia |
| [STATUS.md](STATUS.md) | Punctul exact de reluare și limitările curente |
| [Workflow](docs/workflow.md) | Lucrul pe module, în conversații separate și de pe mai multe PC-uri |
| [Arhitectură](docs/architecture.md) | Cele 30 de livrabile cerute în brief |
| [Decizii](docs/decisions.md) | Ce este stabilit, ce este propus și ce mai trebuie decis |
| [Module](docs/modules/template.md) | Modelul fișei unui modul |
| [Verificare modul 01](docs/testing/01-architecture-review.md) | Raportul istoric al arhitecturii |
| [Setup](docs/setup.md) | Pornire Docker sau Windows nativ, versiuni și comenzi |
| [Verificare modul 02](docs/testing/02-foundation.md) | PHP/MySQL/HTTP și CI |
| [Identitate și magazine](docs/identity.md) | Provisionare, roluri, sesiuni și API |
| [Jurnal inițial](docs/journal/2026-09-27.md) | Schimbările și rezultatele acestei sesiuni |

## Regula de lucru

**Un modul → implementare în pași mici → verificări → actualizare documente → commit/push când Git este configurat → predare.**

Un singur modul este activ. Dacă utilizatorul cere explicit un lot de module, fiecare se închide prin teste, documente și push înaintea următorului. Progresul se păstrează în fișiere versionate, astfel încât reluarea într-o conversație separată să nu depindă de istoricul chatului.

Codul și documentația sunt pe [branch-ul proiectului](https://github.com/razvanstav/ordely/tree/codex/modul-01-arhitectura). Numele branch-ului a fost păstrat; modulul curent se stabilește din plan. Pe alt PC clonează acest branch și urmează setup-ul și [workflow-ul](docs/workflow.md). Modificările viitoare devin disponibile acolo după fiecare push reușit.
