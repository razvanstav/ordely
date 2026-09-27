# Ordely

Platformă SaaS pentru operațiunile magazinelor online: un Core independent de CMS, conectat la platforme de comerț, curieri și servicii de facturare.

**Stadiu: planificare. Arhitectura este propusă, nu aprobată. Aplicația nu este implementată.**

## De unde reluăm

1. Citește [regulile proiectului](AGENTS.md).
2. Citește [starea curentă](STATUS.md) și [planul modulelor](plan.md).
3. Deschide fișa singurului modul activ: [01 — Arhitectură](docs/modules/01-arhitectura.md).
4. Consultă [arhitectura propusă](docs/architecture.md) și [deciziile deschise](docs/decisions.md).

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
| [Verificare modul 01](docs/testing/01-architecture-review.md) | Verificările efectuate și testele încă inexistente |
| [Jurnal inițial](docs/journal/2026-09-27.md) | Schimbările și rezultatele acestei sesiuni |

## Regula de lucru

**Un modul → implementare în pași mici → verificări → actualizare documente → commit/push când Git este configurat → predare.**

O conversație are un singur modul ca obiectiv. Poate acoperi doar un pas din acel modul. Progresul se păstrează în fișiere versionate, astfel încât următoarea conversație să nu depindă de istoricul chatului.

Sincronizarea între PC-uri necesită commit și push/pull reușite. Remote-ul [GitHub Ordely](https://github.com/razvanstav/ordely) este configurat. Identitatea Git a fost lăsată neconfigurată la cererea utilizatorului, deci nu există încă commit/push; fișierele există numai local.
